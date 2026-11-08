<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Api;

use Artimus\Matomo\Api\Data\OrderTrackingInterface;
use Artimus\Matomo\Exception\TrackingFailedException;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Sends a placed order to Matomo as an ecommerce conversion, through the Matomo PHP SDK.
 *
 * @api
 */
interface OrderTrackerInterface
{
    /**
     * Track the order as a Matomo ecommerce conversion.
     *
     * @param OrderInterface $order
     * @param OrderTrackingInterface $record The tracking record captured at order placement.
     * @return void
     * @throws TrackingFailedException
     */
    public function track(OrderInterface $order, OrderTrackingInterface $record): void;
}
