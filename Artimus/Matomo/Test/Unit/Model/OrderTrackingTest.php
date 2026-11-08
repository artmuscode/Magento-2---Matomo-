<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit\Model;

use Artimus\Matomo\Api\Data\OrderTrackingInterface;
use Artimus\Matomo\Model\OrderTracking;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class OrderTrackingTest extends TestCase
{
    public function testItDefaultsNewRecordsToPendingStatusWithZeroAttempts(): void
    {
        $objectManager = new ObjectManager($this);

        /** @var OrderTracking $orderTracking */
        $orderTracking = $objectManager->getObject(OrderTracking::class);

        $this->assertSame(OrderTrackingInterface::STATUS_PENDING, $orderTracking->getStatus());
        $this->assertSame(0, $orderTracking->getAttempts());
    }
}
