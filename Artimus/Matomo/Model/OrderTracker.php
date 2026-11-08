<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Model;

use Artimus\Matomo\Api\ConfigInterface;
use Artimus\Matomo\Api\Data\OrderTrackingInterface;
use Artimus\Matomo\Api\OrderTrackerInterface;
use Artimus\Matomo\Exception\TrackingFailedException;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

/**
 * Sends a placed order to Matomo as an ecommerce conversion, through the Matomo PHP SDK.
 */
class OrderTracker implements OrderTrackerInterface
{
    /**
     * @var TrackerFactory
     */
    private $trackerFactory;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param TrackerFactory $trackerFactory
     * @param ConfigInterface $config
     * @param LoggerInterface $logger
     */
    public function __construct(TrackerFactory $trackerFactory, ConfigInterface $config, LoggerInterface $logger)
    {
        $this->trackerFactory = $trackerFactory;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function track(OrderInterface $order, OrderTrackingInterface $record): void
    {
        $storeId = (int) $order->getStoreId();
        $tracker = $this->trackerFactory->create(
            $this->config->getSiteId($storeId),
            $this->config->getServerTrackerUrl($storeId)
        );

        $authToken = $this->config->getAuthToken($storeId);
        if ($authToken !== '') {
            $tracker->setTokenAuth($authToken);

            // Always force a visitor id (cid). Without one Matomo matches visits by config_id, which every
            // server-side request shares, so a visitor-less order would merge into another order's visit.
            $orderScopedId = substr(hash('sha256', 'artimus-matomo-order:' . $order->getIncrementId()), 0, 16);
            $tracker->setVisitorId($record->getVisitorId() ?? $orderScopedId);

            $tracker->setIp((string) $order->getRemoteIp());
            $tracker->setForceVisitDateTime((string) $order->getCreatedAt());
        } else {
            $this->logger->warning(
                sprintf(
                    'Artimus_Matomo: no auth token configured for store %d; sending order "%s" without'
                    . ' visitor id, ip or forced visit time.',
                    $storeId,
                    (string) $order->getIncrementId()
                )
            );
        }

        if ($record->getUserId() !== null) {
            $tracker->setUserId($record->getUserId());
        }

        foreach ($order->getAllVisibleItems() as $item) {
            $tracker->addEcommerceItem(
                (string) $item->getSku(),
                (string) $item->getName(),
                '',
                (float) $item->getPrice(),
                (int) $item->getQtyOrdered()
            );
        }

        try {
            $response = $tracker->doTrackEcommerceOrder(
                (string) $order->getIncrementId(),
                (float) $order->getGrandTotal(),
                (float) $order->getSubtotal(),
                (float) $order->getTaxAmount(),
                (float) $order->getShippingAmount(),
                abs((float) $order->getDiscountAmount())
            );
        } catch (\Throwable $exception) {
            throw new TrackingFailedException(
                __('Failed to send order "%1" to Matomo.', $order->getIncrementId()),
                $exception
            );
        }

        if ($response === false || $response === '') {
            throw new TrackingFailedException(
                __('Matomo returned an empty or failed response for order "%1".', $order->getIncrementId())
            );
        }
    }
}
