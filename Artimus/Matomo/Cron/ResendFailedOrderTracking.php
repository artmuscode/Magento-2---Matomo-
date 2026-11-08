<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Cron;

use Artimus\Matomo\Api\Data\OrderTrackingInterface;
use Artimus\Matomo\Api\OrderTrackingRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Re-queues failed order tracking records, and pending ones whose queue message was lost.
 *
 * Only republishes; the queue consumer stays the single sender. Records that ran out of attempts
 * or are older than the retry window are abandoned. Never throws.
 */
class ResendFailedOrderTracking
{
    private const TOPIC_ORDER_TRACK = 'artimus.matomo.order.track';
    private const PAGE_SIZE = 100;
    private const PENDING_GRACE_SECONDS = 1800;
    private const MAX_AGE_SECONDS = 259200;

    /**
     * @var OrderTrackingRepositoryInterface
     */
    private $trackingRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $criteriaBuilder;

    /**
     * @var PublisherInterface
     */
    private $publisher;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param OrderTrackingRepositoryInterface $trackingRepository
     * @param SearchCriteriaBuilder $criteriaBuilder
     * @param PublisherInterface $publisher
     * @param OrderRepositoryInterface $orderRepository
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     */
    public function __construct(
        OrderTrackingRepositoryInterface $trackingRepository,
        SearchCriteriaBuilder $criteriaBuilder,
        PublisherInterface $publisher,
        OrderRepositoryInterface $orderRepository,
        DateTime $dateTime,
        LoggerInterface $logger
    ) {
        $this->trackingRepository = $trackingRepository;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->publisher = $publisher;
        $this->orderRepository = $orderRepository;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
    }

    /**
     * Cron entry point.
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            $now = (int) $this->dateTime->gmtTimestamp();
            foreach ($this->findCandidates($now) as $record) {
                $this->handle($record, $now);
            }
        } catch (\Throwable $exception) {
            $this->logger->error('Artimus_Matomo: order tracking resend failed: ' . $exception->getMessage());
        }
    }

    /**
     * Select failed records plus pending records past the grace period.
     *
     * Deliberately no lower date bound, so records past the retry window are selected and abandoned.
     *
     * @param int $now
     * @return OrderTrackingInterface[]
     */
    private function findCandidates(int $now): array
    {
        $failed = $this->criteriaBuilder
            ->addFilter(OrderTrackingInterface::STATUS, OrderTrackingInterface::STATUS_FAILED)
            ->setPageSize(self::PAGE_SIZE)
            ->create();
        $pending = $this->criteriaBuilder
            ->addFilter(OrderTrackingInterface::STATUS, OrderTrackingInterface::STATUS_PENDING)
            ->addFilter(
                OrderTrackingInterface::CREATED_AT,
                gmdate('Y-m-d H:i:s', $now - self::PENDING_GRACE_SECONDS),
                'lt'
            )
            ->setPageSize(self::PAGE_SIZE)
            ->create();

        return array_merge(
            $this->trackingRepository->getList($failed)->getItems(),
            $this->trackingRepository->getList($pending)->getItems()
        );
    }

    /**
     * Abandon or republish one record.
     *
     * @param OrderTrackingInterface $record
     * @param int $now
     * @return void
     */
    private function handle(OrderTrackingInterface $record, int $now): void
    {
        $age = $now - (int) strtotime($record->getCreatedAt() . ' UTC');

        if ($age > self::MAX_AGE_SECONDS || (int) $record->getAttempts() >= OrderTrackingInterface::MAX_ATTEMPTS) {
            $this->abandon($record);
            return;
        }
        if ($record->getStatus() === OrderTrackingInterface::STATUS_PENDING
            && $age < self::PENDING_GRACE_SECONDS
        ) {
            return;
        }

        try {
            $this->publisher->publish(self::TOPIC_ORDER_TRACK, (int) $record->getEntityId());
        } catch (\Throwable $exception) {
            $this->logger->error(sprintf(
                'Artimus_Matomo: could not republish tracking record %d: %s',
                (int) $record->getEntityId(),
                $exception->getMessage()
            ));
        }
    }

    /**
     * Give up on a record and log the order it belonged to.
     *
     * @param OrderTrackingInterface $record
     * @return void
     */
    private function abandon(OrderTrackingInterface $record): void
    {
        try {
            $record->setStatus(OrderTrackingInterface::STATUS_ABANDONED);
            $this->trackingRepository->save($record);
            $this->logger->error(sprintf(
                'Artimus_Matomo: abandoned tracking for order %s after %d attempts.',
                $this->incrementId($record),
                (int) $record->getAttempts()
            ));
        } catch (\Throwable $exception) {
            $this->logger->error(sprintf(
                'Artimus_Matomo: could not abandon tracking record %d: %s',
                (int) $record->getEntityId(),
                $exception->getMessage()
            ));
        }
    }

    /**
     * Order increment ID, or a placeholder when the order is gone.
     *
     * @param OrderTrackingInterface $record
     * @return string
     */
    private function incrementId(OrderTrackingInterface $record): string
    {
        try {
            return (string) $this->orderRepository->get((int) $record->getOrderId())->getIncrementId();
        } catch (NoSuchEntityException $exception) {
            return sprintf('(missing, order ID %d)', (int) $record->getOrderId());
        }
    }
}
