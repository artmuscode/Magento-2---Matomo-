<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit\Model;

use Artimus\Matomo\Api\ConfigInterface;
use Artimus\Matomo\Api\Data\OrderTrackingInterface;
use Artimus\Matomo\Exception\TrackingFailedException;
use Artimus\Matomo\Model\OrderTracker;
use Artimus\Matomo\Model\TrackerFactory;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OrderTrackerTest extends TestCase
{
    private const STORE_ID = 7;
    private const SITE_ID = 3;
    private const SERVER_TRACKER_URL = 'https://matomo.test/';
    private const AUTH_TOKEN = 'auth-token-value';

    /**
     * @var ConfigInterface&MockObject
     */
    private $config;

    /**
     * @var TrackerFactory&MockObject
     */
    private $trackerFactory;

    /**
     * @var \MatomoTracker&MockObject
     */
    private $matomoTracker;

    /**
     * @var LoggerInterface&MockObject
     */
    private $logger;

    /**
     * @var OrderTracker
     */
    private $orderTracker;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->config->method('getSiteId')->with(self::STORE_ID)->willReturn(self::SITE_ID);
        $this->config->method('getServerTrackerUrl')->with(self::STORE_ID)->willReturn(self::SERVER_TRACKER_URL);
        $this->config->method('getAuthToken')->with(self::STORE_ID)->willReturn(self::AUTH_TOKEN);

        $this->matomoTracker = $this->createMock(\MatomoTracker::class);

        $this->trackerFactory = $this->createMock(TrackerFactory::class);
        $this->trackerFactory->method('create')
            ->with(self::SITE_ID, self::SERVER_TRACKER_URL)
            ->willReturn($this->matomoTracker);

        $this->logger = $this->createMock(LoggerInterface::class);

        $this->orderTracker = new OrderTracker($this->trackerFactory, $this->config, $this->logger);
    }

    /**
     * @param array $overrides
     * @return Order&MockObject
     */
    private function createOrder(array $overrides = [])
    {
        $order = $this->createMock(Order::class);
        $defaults = [
            'getStoreId' => self::STORE_ID,
            'getIncrementId' => '000000123',
            'getGrandTotal' => 109.98,
            'getSubtotal' => 99.98,
            'getTaxAmount' => 10.0,
            'getShippingAmount' => 0.0,
            'getDiscountAmount' => 0.0,
            'getRemoteIp' => '203.0.113.5',
            'getCreatedAt' => '2026-09-30 12:00:00',
            'getAllVisibleItems' => [],
        ];
        foreach (array_merge($defaults, $overrides) as $method => $value) {
            $order->method($method)->willReturn($value);
        }

        return $order;
    }

    /**
     * @param array $overrides
     * @return OrderTrackingInterface&MockObject
     */
    private function createRecord(array $overrides = [])
    {
        $record = $this->createMock(OrderTrackingInterface::class);
        $defaults = [
            'getVisitorId' => null,
            'getUserId' => null,
        ];
        foreach (array_merge($defaults, $overrides) as $method => $value) {
            $record->method($method)->willReturn($value);
        }

        return $record;
    }

    /**
     * @param string $sku
     * @param string $name
     * @param float $price
     * @param float $qtyOrdered
     * @return OrderItemInterface&MockObject
     */
    private function createItem(string $sku, string $name, float $price, float $qtyOrdered)
    {
        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getSku')->willReturn($sku);
        $item->method('getName')->willReturn($name);
        $item->method('getPrice')->willReturn($price);
        $item->method('getQtyOrdered')->willReturn($qtyOrdered);

        return $item;
    }

    public function testItAddsOneEcommerceItemPerVisibleOrderItemWithSkuNamePriceAndQuantity(): void
    {
        $itemOne = $this->createItem('SKU-1', 'Product One', 19.99, 2.0);
        $itemTwo = $this->createItem('SKU-2', 'Product Two', 49.0, 1.0);
        $order = $this->createOrder(['getAllVisibleItems' => [$itemOne, $itemTwo]]);
        $record = $this->createRecord();

        $this->matomoTracker->method('doTrackEcommerceOrder')->willReturn('tracked');

        $calls = [];
        $this->matomoTracker->expects($this->exactly(2))
            ->method('addEcommerceItem')
            ->willReturnCallback(function (...$args) use (&$calls) {
                $calls[] = $args;

                return $this->matomoTracker;
            });

        $this->orderTracker->track($order, $record);

        $this->assertSame(['SKU-1', 'Product One', '', 19.99, 2], $calls[0]);
        $this->assertSame(['SKU-2', 'Product Two', '', 49.0, 1], $calls[1]);
    }

    public function testItTracksTheOrderWithIncrementIdGrandTotalSubtotalTaxShippingAndDiscount(): void
    {
        $order = $this->createOrder(
            [
                'getIncrementId' => '000000123',
                'getGrandTotal' => 109.98,
                'getSubtotal' => 99.98,
                'getTaxAmount' => 10.0,
                'getShippingAmount' => 5.0,
                'getDiscountAmount' => 0.0,
            ]
        );
        $record = $this->createRecord();

        $this->matomoTracker->expects($this->once())
            ->method('doTrackEcommerceOrder')
            ->with('000000123', 109.98, 99.98, 10.0, 5.0, 0.0)
            ->willReturn('tracked');

        $this->orderTracker->track($order, $record);
    }

    public function testItSendsTheDiscountAsAPositiveAmount(): void
    {
        $order = $this->createOrder(['getDiscountAmount' => -15.5]);
        $record = $this->createRecord();

        $this->matomoTracker->expects($this->once())
            ->method('doTrackEcommerceOrder')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                15.5
            )
            ->willReturn('tracked');

        $this->orderTracker->track($order, $record);
    }

    public function testItSetsTheCapturedVisitorIdOnTheTracker(): void
    {
        $order = $this->createOrder();
        $record = $this->createRecord(['getVisitorId' => 'abcdef0123456789']);
        $this->matomoTracker->method('doTrackEcommerceOrder')->willReturn('tracked');

        $this->matomoTracker->expects($this->once())
            ->method('setVisitorId')
            ->with('abcdef0123456789');

        $this->orderTracker->track($order, $record);
    }

    /**
     * Regression (task 014): without a forced visitor id Matomo falls back to config_id matching, and
     * every server-side request shares one config_id (SDK user agent + order IP). An order without a
     * captured visitor id then merged into another order's visit and overwrote its visitor id.
     */
    public function testItForcesAnOrderScopedVisitorIdWhenNoneWasCaptured(): void
    {
        $sent = [];
        $this->matomoTracker->method('setVisitorId')->willReturnCallback(
            function (string $visitorId) use (&$sent) {
                $sent[] = $visitorId;
                return $this->matomoTracker;
            }
        );
        $this->matomoTracker->method('doTrackEcommerceOrder')->willReturn('tracked');

        $record = $this->createRecord(['getVisitorId' => null]);
        $this->orderTracker->track($this->createOrder(['getIncrementId' => '000000123']), $record);
        $this->orderTracker->track($this->createOrder(['getIncrementId' => '000000123']), $record);
        $this->orderTracker->track($this->createOrder(['getIncrementId' => '000000124']), $record);

        $this->assertCount(3, $sent);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $sent[0]);
        $this->assertSame($sent[0], $sent[1], 'a retried order must land in the same visit');
        $this->assertNotSame($sent[0], $sent[2], 'different orders must not share a visitor');
    }

    public function testItSetsTheUserIdWhenTheRecordHasOne(): void
    {
        $order = $this->createOrder();
        $record = $this->createRecord(['getUserId' => 'hashed-user-id']);
        $this->matomoTracker->method('doTrackEcommerceOrder')->willReturn('tracked');

        $this->matomoTracker->expects($this->once())
            ->method('setUserId')
            ->with('hashed-user-id');

        $this->orderTracker->track($order, $record);
    }

    public function testItBuildsTheTrackerWithTheStoresSiteIdServerTrackerUrlAndAuthToken(): void
    {
        $order = $this->createOrder(['getStoreId' => self::STORE_ID]);
        $record = $this->createRecord();
        $this->matomoTracker->method('doTrackEcommerceOrder')->willReturn('tracked');

        $this->trackerFactory->expects($this->once())
            ->method('create')
            ->with(self::SITE_ID, self::SERVER_TRACKER_URL)
            ->willReturn($this->matomoTracker);
        $this->matomoTracker->expects($this->once())
            ->method('setTokenAuth')
            ->with(self::AUTH_TOKEN);

        $this->orderTracker->track($order, $record);
    }

    public function testItThrowsTrackingFailedExceptionWhenTheSdkRequestFails(): void
    {
        $order = $this->createOrder();
        $record = $this->createRecord();

        $this->matomoTracker->method('doTrackEcommerceOrder')
            ->willThrowException(new \Exception('Matomo server unreachable'));

        $this->expectException(TrackingFailedException::class);

        $this->orderTracker->track($order, $record);
    }

    public function testItThrowsTrackingFailedExceptionWhenTheResponseIsEmptyOrFalse(): void
    {
        $order = $this->createOrder();
        $record = $this->createRecord();

        $this->matomoTracker->method('doTrackEcommerceOrder')->willReturn(false);

        $this->expectException(TrackingFailedException::class);

        $this->orderTracker->track($order, $record);
    }

    public function testItForcesTheVisitDateTimeToTheOrderCreationTime(): void
    {
        $order = $this->createOrder(['getCreatedAt' => '2026-09-30 12:34:56']);
        $record = $this->createRecord();
        $this->matomoTracker->method('doTrackEcommerceOrder')->willReturn('tracked');

        $this->matomoTracker->expects($this->once())
            ->method('setForceVisitDateTime')
            ->with('2026-09-30 12:34:56');

        $this->orderTracker->track($order, $record);
    }

    public function testItOmitsVisitorIdIpAndForcedTimeWhenNoAuthTokenIsConfigured(): void
    {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->config->method('getSiteId')->with(self::STORE_ID)->willReturn(self::SITE_ID);
        $this->config->method('getServerTrackerUrl')->with(self::STORE_ID)->willReturn(self::SERVER_TRACKER_URL);
        $this->config->method('getAuthToken')->with(self::STORE_ID)->willReturn('');
        $this->orderTracker = new OrderTracker($this->trackerFactory, $this->config, $this->logger);

        $order = $this->createOrder();
        $record = $this->createRecord(['getVisitorId' => 'abcdef0123456789']);
        $this->matomoTracker->method('doTrackEcommerceOrder')->willReturn('tracked');

        $this->matomoTracker->expects($this->never())->method('setVisitorId');
        $this->matomoTracker->expects($this->never())->method('setIp');
        $this->matomoTracker->expects($this->never())->method('setForceVisitDateTime');
        $this->logger->expects($this->once())->method('warning');

        $this->orderTracker->track($order, $record);
    }
}
