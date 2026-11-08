<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Model\Queue;

use Artimus\Matomo\Api\Data\OrderTrackingInterface;
use Artimus\Matomo\Api\OrderTrackerInterface;
use Artimus\Matomo\Api\OrderTrackingRepositoryInterface;
use Artimus\Matomo\Exception\TrackingFailedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Queue consumer for `artimus.matomo.order.track`: sends one order to Matomo and records the outcome.
 *
 * Never rethrows and never republishes: a failed record stays `failed` for the retry cron job, and a
 * rethrown exception would make the DB queue redeliver the message immediately, inside the same outage.
 */
class OrderTrackingConsumer
{
    private const MAX_ERROR_LENGTH = 255;

    /**
     * @var OrderTrackingRepositoryInterface
     */
    private $trackingRepository;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var OrderTrackerInterface
     */
    private $orderTracker;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param OrderTrackingRepositoryInterface $trackingRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderTrackerInterface $orderTracker
     * @param LoggerInterface $logger
     */
    public function __construct(
        OrderTrackingRepositoryInterface $trackingRepository,
        OrderRepositoryInterface $orderRepository,
        OrderTrackerInterface $orderTracker,
        LoggerInterface $logger
    ) {
        $this->trackingRepository = $trackingRepository;
        $this->orderRepository = $orderRepository;
        $this->orderTracker = $orderTracker;
        $this->logger = $logger;
    }

    /**
     * Track the order of the given tracking record.
     *
     * @param int $trackingId
     * @return void
     */
    public function process(int $trackingId): void
    {
        try {
            $record = $this->trackingRepository->getById($trackingId);
            $order = $this->orderRepository->get((int) $record->getOrderId());
        } catch (NoSuchEntityException $exception) {
            $this->logger->warning(
                sprintf('Artimus_Matomo: record %d or its order no longer exists; dropping message.', $trackingId)
            );
            return;
        }

        if (!$this->isSendable($record)) {
            return;
        }

        try {
            $record->setAttempts((int) $record->getAttempts() + 1);
            $this->trackingRepository->save($record);

            $this->orderTracker->track($order, $record);

            $record->setStatus(OrderTrackingInterface::STATUS_SENT);
            $record->setSentAt(gmdate('Y-m-d H:i:s'));
            $record->setLastError(null);
            $this->trackingRepository->save($record);
        } catch (TrackingFailedException $exception) {
            $this->logger->warning(
                sprintf('Artimus_Matomo: tracking record %d failed: %s', $trackingId, $exception->getMessage())
            );
            $this->markFailed($record, $exception->getMessage());
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf(
                    'Artimus_Matomo: unexpected error for tracking record %d: %s',
                    $trackingId,
                    $this->sanitize($exception->getMessage())
                )
            );
            $this->markFailed($record, get_class($exception) . ': ' . $exception->getMessage());
        }
    }

    /**
     * Only pending or failed records with attempts left are sent.
     *
     * @param OrderTrackingInterface $record
     * @return bool
     */
    private function isSendable(OrderTrackingInterface $record): bool
    {
        return in_array(
            $record->getStatus(),
            [OrderTrackingInterface::STATUS_PENDING, OrderTrackingInterface::STATUS_FAILED],
            true
        ) && (int) $record->getAttempts() < OrderTrackingInterface::MAX_ATTEMPTS;
    }

    /**
     * Persist the failed status; a save error must not escape and cause redelivery.
     *
     * @param OrderTrackingInterface $record
     * @param string $error
     * @return void
     */
    private function markFailed(OrderTrackingInterface $record, string $error): void
    {
        try {
            $record->setStatus(OrderTrackingInterface::STATUS_FAILED);
            $record->setLastError($this->sanitize($error));
            $this->trackingRepository->save($record);
        } catch (\Throwable $exception) {
            $this->logger->error('Artimus_Matomo: could not save failed tracking record: ' . $exception->getMessage());
        }
    }

    /**
     * Strip any auth token and truncate to the column length.
     *
     * @param string $message
     * @return string
     */
    private function sanitize(string $message): string
    {
        $message = (string) preg_replace('/token_auth=[^&\s"\']*/i', 'token_auth=***', $message);
        return mb_substr($message, 0, self::MAX_ERROR_LENGTH);
    }
}
