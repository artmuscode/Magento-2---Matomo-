<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit\Model\Queue;

use Artimus\Matomo\Api\Data\OrderTrackingInterface;
use Artimus\Matomo\Api\OrderTrackerInterface;
use Artimus\Matomo\Api\OrderTrackingRepositoryInterface;
use Artimus\Matomo\Exception\TrackingFailedException;
use Artimus\Matomo\Model\Queue\OrderTrackingConsumer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OrderTrackingConsumerTest extends TestCase
{
    private const TRACKING_ID = 5;
    private const ORDER_ID = 42;

    /** @var OrderTrackingRepositoryInterface&MockObject */
    private $repository;

    /** @var OrderRepositoryInterface&MockObject */
    private $orderRepository;

    /** @var OrderTrackerInterface&MockObject */
    private $tracker;

    /** @var LoggerInterface&MockObject */
    private $logger;

    /** @var OrderInterface&MockObject */
    private $order;

    /** @var OrderTrackingConsumer */
    private $consumer;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(OrderTrackingRepositoryInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->tracker = $this->createMock(OrderTrackerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->order = $this->createMock(OrderInterface::class);
        $this->consumer = new OrderTrackingConsumer(
            $this->repository,
            $this->orderRepository,
            $this->tracker,
            $this->logger
        );
    }

    /**
     * Record stub; the DB layer returns attempts as a numeric string.
     *
     * @param string $status
     * @param string|int $attempts
     * @return OrderTrackingInterface&MockObject
     */
    private function record(string $status, $attempts = '0'): OrderTrackingInterface
    {
        $record = $this->createMock(OrderTrackingInterface::class);
        $record->method('getStatus')->willReturn($status);
        $record->method('getAttempts')->willReturn($attempts);
        $record->method('getOrderId')->willReturn((string) self::ORDER_ID);
        $this->repository->method('getById')->with(self::TRACKING_ID)->willReturn($record);
        $this->orderRepository->method('get')->with(self::ORDER_ID)->willReturn($this->order);
        return $record;
    }

    public function testItMarksTheRecordSentAfterASuccessfulTrack(): void
    {
        $record = $this->record(OrderTrackingInterface::STATUS_PENDING);
        $this->tracker->expects($this->once())->method('track')->with($this->order, $record);
        $record->expects($this->once())->method('setStatus')->with(OrderTrackingInterface::STATUS_SENT);
        $record->expects($this->once())->method('setSentAt')->with($this->matchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/'
        ));
        $record->expects($this->once())->method('setLastError')->with(null);

        $this->consumer->process(self::TRACKING_ID);
    }

    public function testItIncrementsAttemptsBeforeCallingTheTracker(): void
    {
        $record = $this->record(OrderTrackingInterface::STATUS_FAILED, '2');
        $calls = [];
        $record->method('setAttempts')->willReturnCallback(function ($n) use (&$calls, $record) {
            $calls[] = 'attempts=' . $n;
            return $record;
        });
        $this->repository->method('save')->willReturnCallback(function () use (&$calls, $record) {
            $calls[] = 'save';
            return $record;
        });
        $this->tracker->method('track')->willReturnCallback(function () use (&$calls) {
            $calls[] = 'track';
        });

        $this->consumer->process(self::TRACKING_ID);

        $this->assertSame(['attempts=3', 'save', 'track', 'save'], $calls);
    }

    public function testItSkipsRecordsThatAreAlreadySentOrSkipped(): void
    {
        foreach ([OrderTrackingInterface::STATUS_SENT, OrderTrackingInterface::STATUS_SKIPPED] as $status) {
            $this->setUp();
            $this->record($status);
            $this->tracker->expects($this->never())->method('track');
            $this->repository->expects($this->never())->method('save');

            $this->consumer->process(self::TRACKING_ID);
        }
    }

    public function testItMarksTheRecordFailedWithTheErrorAndDoesNotRepublishWhenTrackingFails(): void
    {
        $record = $this->record(OrderTrackingInterface::STATUS_PENDING);
        $long = str_repeat('x', 1000);
        $this->tracker->method('track')->willThrowException(
            new TrackingFailedException(__($long), new \RuntimeException('url?token_auth=secret123'))
        );
        $record->expects($this->once())->method('setStatus')->with(OrderTrackingInterface::STATUS_FAILED);
        $record->expects($this->once())->method('setLastError')->with($this->callback(
            static fn ($e) => strlen($e) <= 255 && strpos($e, 'secret123') === false
        ));
        $this->logger->expects($this->once())->method('warning');

        $this->consumer->process(self::TRACKING_ID);
    }

    public function testItMarksTheRecordFailedAndStripsTheTokenOnUnexpectedErrors(): void
    {
        $record = $this->record(OrderTrackingInterface::STATUS_PENDING);
        $this->tracker->method('track')->willThrowException(
            new \RuntimeException('GET http://m/?token_auth=secret123&x=1 failed')
        );
        $record->expects($this->once())->method('setStatus')->with(OrderTrackingInterface::STATUS_FAILED);
        $record->expects($this->once())->method('setLastError')->with($this->callback(
            static fn ($e) => strpos($e, 'secret123') === false && strpos($e, 'failed') !== false
        ));
        $this->logger->expects($this->once())->method('error');

        $this->consumer->process(self::TRACKING_ID);
    }

    public function testItSkipsRecordsThatHaveReachedTheMaximumAttempts(): void
    {
        $this->record(OrderTrackingInterface::STATUS_FAILED, (string) OrderTrackingInterface::MAX_ATTEMPTS);
        $this->tracker->expects($this->never())->method('track');
        $this->repository->expects($this->never())->method('save');

        $this->consumer->process(self::TRACKING_ID);
    }

    public function testItAcknowledgesAndLogsAMessageWhoseRecordOrOrderNoLongerExists(): void
    {
        $this->repository->method('getById')->willThrowException(new NoSuchEntityException());
        $this->logger->expects($this->once())->method('warning');
        $this->tracker->expects($this->never())->method('track');

        $this->consumer->process(self::TRACKING_ID);
    }

    public function testItAcknowledgesAndLogsAMessageWhoseOrderNoLongerExists(): void
    {
        $record = $this->createMock(OrderTrackingInterface::class);
        $record->method('getOrderId')->willReturn(self::ORDER_ID);
        $this->repository->method('getById')->willReturn($record);
        $this->orderRepository->method('get')->willThrowException(new NoSuchEntityException());
        $this->logger->expects($this->once())->method('warning');
        $this->tracker->expects($this->never())->method('track');

        $this->consumer->process(self::TRACKING_ID);
    }
}
