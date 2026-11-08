<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit\Cron;

use Artimus\Matomo\Api\Data\OrderTrackingInterface;
use Artimus\Matomo\Api\Data\OrderTrackingSearchResultsInterface;
use Artimus\Matomo\Api\OrderTrackingRepositoryInterface;
use Artimus\Matomo\Cron\ResendFailedOrderTracking;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ResendFailedOrderTrackingTest extends TestCase
{
    private const NOW = 1790000000;

    /** @var OrderTrackingRepositoryInterface&MockObject */
    private $repository;

    /** @var SearchCriteriaBuilder&MockObject */
    private $criteriaBuilder;

    /** @var PublisherInterface&MockObject */
    private $publisher;

    /** @var OrderRepositoryInterface&MockObject */
    private $orderRepository;

    /** @var LoggerInterface&MockObject */
    private $logger;

    /** @var array */
    private $filters = [];

    /** @var ResendFailedOrderTracking */
    private $cron;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(OrderTrackingRepositoryInterface::class);
        $this->publisher = $this->createMock(PublisherInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturn(self::NOW);

        $this->criteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->criteriaBuilder->method('addFilter')->willReturnCallback(
            function ($field, $value, $condition = 'eq') {
                $this->filters[] = [$field, $value, $condition];
                return $this->criteriaBuilder;
            }
        );
        $this->criteriaBuilder->method('setPageSize')->willReturnSelf();
        $this->criteriaBuilder->method('create')->willReturn($this->createMock(SearchCriteriaInterface::class));

        $this->cron = new ResendFailedOrderTracking(
            $this->repository,
            $this->criteriaBuilder,
            $this->publisher,
            $this->orderRepository,
            $dateTime,
            $this->logger
        );
    }

    /**
     * DB-shaped record: attempts and ids as strings, created_at as a UTC string.
     */
    private function record(int $id, string $status, int $attempts, int $ageSeconds): OrderTrackingInterface
    {
        $record = $this->createMock(OrderTrackingInterface::class);
        $record->method('getEntityId')->willReturn((string) $id);
        $record->method('getOrderId')->willReturn((string) (100 + $id));
        $record->method('getStatus')->willReturn($status);
        $record->method('getAttempts')->willReturn((string) $attempts);
        $record->method('getCreatedAt')->willReturn(gmdate('Y-m-d H:i:s', self::NOW - $ageSeconds));
        return $record;
    }

    /**
     * @param OrderTrackingInterface[] $failed
     * @param OrderTrackingInterface[] $pending
     */
    private function givenCandidates(array $failed, array $pending = []): void
    {
        $results = [];
        foreach ([$failed, $pending] as $items) {
            $result = $this->createMock(OrderTrackingSearchResultsInterface::class);
            $result->method('getItems')->willReturn($items);
            $results[] = $result;
        }
        $this->repository->method('getList')->willReturnOnConsecutiveCalls(...$results);
    }

    public function testItRepublishesFailedRecordsBelowTheMaximumAttempts(): void
    {
        $this->givenCandidates([$this->record(5, 'failed', 3, 3600)]);

        $this->publisher->expects($this->once())->method('publish')
            ->with('artimus.matomo.order.track', 5);

        $this->cron->execute();
    }

    public function testItRepublishesPendingRecordsOlderThanThirtyMinutes(): void
    {
        $this->givenCandidates([], [$this->record(6, 'pending', 0, 31 * 60)]);

        $this->publisher->expects($this->once())->method('publish')
            ->with('artimus.matomo.order.track', 6);

        $this->cron->execute();

        $cutoff = gmdate('Y-m-d H:i:s', self::NOW - 1800);
        $this->assertContains(['created_at', $cutoff, 'lt'], $this->filters);
    }

    public function testItDoesNotRepublishPendingRecordsYoungerThanThirtyMinutes(): void
    {
        $this->givenCandidates([], [$this->record(7, 'pending', 0, 29 * 60)]);

        $this->publisher->expects($this->never())->method('publish');

        $this->cron->execute();
    }

    public function testItMarksRecordsAtTheMaximumAttemptsAsAbandonedWithoutRepublishing(): void
    {
        $record = $this->record(8, 'failed', OrderTrackingInterface::MAX_ATTEMPTS, 3600);
        $this->givenCandidates([$record]);
        $order = $this->createMock(OrderInterface::class);
        $order->method('getIncrementId')->willReturn('000000042');
        $this->orderRepository->method('get')->with(108)->willReturn($order);

        $record->expects($this->once())->method('setStatus')->with('abandoned');
        $this->repository->expects($this->once())->method('save')->with($record);
        $this->publisher->expects($this->never())->method('publish');
        $this->logger->expects($this->once())->method('error')
            ->with($this->stringContains('000000042'));

        $this->cron->execute();
    }

    public function testItMarksFailedRecordsOlderThanSeventyTwoHoursAsAbandoned(): void
    {
        $record = $this->record(9, 'failed', 2, 73 * 3600);
        $this->givenCandidates([$record]);
        $this->orderRepository->method('get')->willThrowException(new NoSuchEntityException());

        $record->expects($this->once())->method('setStatus')->with('abandoned');
        $this->repository->expects($this->once())->method('save')->with($record);
        $this->publisher->expects($this->never())->method('publish');
        $this->logger->expects($this->once())->method('error');

        $this->cron->execute();
    }

    public function testItContinuesWithRemainingRecordsWhenPublishingOneFails(): void
    {
        $this->givenCandidates([$this->record(1, 'failed', 1, 3600), $this->record(2, 'failed', 1, 3600)]);

        $this->publisher->expects($this->exactly(2))->method('publish')
            ->willReturnCallback(function ($topic, $id) {
                if ($id === 1) {
                    throw new \RuntimeException('queue down');
                }
            });
        $this->logger->expects($this->once())->method('error')->with($this->stringContains('queue down'));

        $this->cron->execute();
    }
}
