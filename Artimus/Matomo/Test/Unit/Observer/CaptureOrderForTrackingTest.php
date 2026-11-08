<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit\Observer;

use Artimus\Matomo\Api\ConfigInterface;
use Artimus\Matomo\Api\ConsentCheckerInterface;
use Artimus\Matomo\Api\Data\OrderTrackingInterface;
use Artimus\Matomo\Api\OrderTrackingRepositoryInterface;
use Artimus\Matomo\Api\UserIdHasherInterface;
use Artimus\Matomo\Model\OrderTracking;
use Artimus\Matomo\Model\OrderTrackingFactory;
use Artimus\Matomo\Model\VisitorIdResolver;
use Artimus\Matomo\Observer\CaptureOrderForTracking;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CaptureOrderForTrackingTest extends TestCase
{
    private const TOPIC = 'artimus.matomo.order.track';

    /**
     * @var ConfigInterface&MockObject
     */
    private $config;

    /**
     * @var ConsentCheckerInterface&MockObject
     */
    private $consentChecker;

    /**
     * @var VisitorIdResolver&MockObject
     */
    private $visitorIdResolver;

    /**
     * @var UserIdHasherInterface&MockObject
     */
    private $userIdHasher;

    /**
     * @var OrderTrackingFactory&MockObject
     */
    private $orderTrackingFactory;

    /**
     * @var OrderTrackingRepositoryInterface&MockObject
     */
    private $orderTrackingRepository;

    /**
     * @var PublisherInterface&MockObject
     */
    private $publisher;

    /**
     * @var LoggerInterface&MockObject
     */
    private $logger;

    /**
     * @var CaptureOrderForTracking
     */
    private CaptureOrderForTracking $observer;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->consentChecker = $this->createMock(ConsentCheckerInterface::class);
        $this->visitorIdResolver = $this->createMock(VisitorIdResolver::class);
        $this->userIdHasher = $this->createMock(UserIdHasherInterface::class);
        $this->orderTrackingFactory = $this->createMock(OrderTrackingFactory::class);
        $this->orderTrackingRepository = $this->createMock(OrderTrackingRepositoryInterface::class);
        $this->publisher = $this->createMock(PublisherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->observer = new CaptureOrderForTracking(
            $this->config,
            $this->consentChecker,
            $this->visitorIdResolver,
            $this->userIdHasher,
            $this->orderTrackingFactory,
            $this->orderTrackingRepository,
            $this->publisher,
            $this->logger
        );
    }

    /**
     * @param Order|null $order
     * @return EventObserver&MockObject
     */
    private function observerWithOrder(?Order $order)
    {
        $event = $this->getMockBuilder(Event::class)
            ->addMethods(['getOrder'])
            ->disableOriginalConstructor()
            ->getMock();
        $event->method('getOrder')->willReturn($order);

        $eventObserver = $this->createPartialMock(EventObserver::class, ['getEvent']);
        $eventObserver->method('getEvent')->willReturn($event);

        return $eventObserver;
    }

    public function testItSavesAPendingRecordWithVisitorIdAndPublishesItsIntIdWhenActiveAndConsented(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getEntityId')->willReturn(55);
        $order->method('getCustomerId')->willReturn(null);
        $order->method('getIncrementId')->willReturn('000000055');

        $this->config->method('isTrackingActive')->with(1)->willReturn(true);
        $this->consentChecker->method('hasConsent')->with(1)->willReturn(true);
        $this->config->method('getSiteId')->with(1)->willReturn(3);
        $this->visitorIdResolver->method('resolve')->with(3)->willReturn('1234567890abcdef');

        $record = $this->createMock(OrderTracking::class);
        $this->orderTrackingFactory->method('create')->willReturn($record);

        $record->expects($this->once())->method('setOrderId')->with(55);
        $record->expects($this->once())->method('setStoreId')->with(1);
        $record->expects($this->once())->method('setVisitorId')->with('1234567890abcdef');
        $record->expects($this->once())->method('setStatus')->with(OrderTrackingInterface::STATUS_PENDING);
        $record->method('getEntityId')->willReturn('77');

        $this->orderTrackingRepository->expects($this->once())
            ->method('save')
            ->with($record)
            ->willReturn($record);

        $this->publisher->expects($this->once())
            ->method('publish')
            ->with(
                self::TOPIC,
                $this->callback(static fn ($value) => $value === 77)
            );

        $this->observer->execute($this->observerWithOrder($order));
    }

    public function testItSavesASkippedRecordAndPublishesNothingWhenConsentIsNotGiven(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getEntityId')->willReturn(55);

        $this->config->method('isTrackingActive')->with(1)->willReturn(true);
        $this->consentChecker->method('hasConsent')->with(1)->willReturn(false);

        $record = $this->createMock(OrderTracking::class);
        $this->orderTrackingFactory->method('create')->willReturn($record);

        $record->expects($this->once())->method('setStatus')->with(OrderTrackingInterface::STATUS_SKIPPED);
        $record->expects($this->never())->method('setVisitorId');

        $this->visitorIdResolver->expects($this->never())->method('resolve');
        $this->orderTrackingRepository->expects($this->once())->method('save')->with($record)->willReturn($record);
        $this->publisher->expects($this->never())->method('publish');

        $this->observer->execute($this->observerWithOrder($order));
    }

    public function testItDoesNothingWhenTrackingIsInactiveForTheOrdersStore(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(1);

        $this->config->method('isTrackingActive')->with(1)->willReturn(false);

        $this->orderTrackingFactory->expects($this->never())->method('create');
        $this->orderTrackingRepository->expects($this->never())->method('save');
        $this->publisher->expects($this->never())->method('publish');
        $this->logger->expects($this->never())->method('error');

        $this->observer->execute($this->observerWithOrder($order));
    }

    public function testItDoesNothingWhenTheEventCarriesNoSingleOrder(): void
    {
        $this->orderTrackingFactory->expects($this->never())->method('create');
        $this->orderTrackingRepository->expects($this->never())->method('save');
        $this->publisher->expects($this->never())->method('publish');
        $this->logger->expects($this->never())->method('error');

        $this->observer->execute($this->observerWithOrder(null));
    }

    public function testItStoresTheHashedUserIdOnlyForARegisteredCustomerWithUserIdTrackingEnabled(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getEntityId')->willReturn(55);
        $order->method('getCustomerId')->willReturn(42);

        $this->config->method('isTrackingActive')->with(1)->willReturn(true);
        $this->consentChecker->method('hasConsent')->with(1)->willReturn(true);
        $this->config->method('getSiteId')->with(1)->willReturn(3);
        $this->visitorIdResolver->method('resolve')->willReturn(null);
        $this->config->method('isUserIdTrackingEnabled')->with(1)->willReturn(true);
        $this->userIdHasher->method('hash')->with(42)->willReturn('hashed-user-id');

        $record = $this->createMock(OrderTracking::class);
        $this->orderTrackingFactory->method('create')->willReturn($record);
        $record->method('getEntityId')->willReturn('77');
        $this->orderTrackingRepository->method('save')->willReturn($record);

        $record->expects($this->once())->method('setUserId')->with('hashed-user-id');

        $this->observer->execute($this->observerWithOrder($order));
    }

    public function testItDoesNotStoreAUserIdForAGuestOrder(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getEntityId')->willReturn(55);
        $order->method('getCustomerId')->willReturn(null);

        $this->config->method('isTrackingActive')->with(1)->willReturn(true);
        $this->consentChecker->method('hasConsent')->with(1)->willReturn(true);
        $this->config->method('getSiteId')->with(1)->willReturn(3);
        $this->visitorIdResolver->method('resolve')->willReturn(null);
        $this->config->method('isUserIdTrackingEnabled')->with(1)->willReturn(true);

        $record = $this->createMock(OrderTracking::class);
        $this->orderTrackingFactory->method('create')->willReturn($record);
        $record->method('getEntityId')->willReturn('77');
        $this->orderTrackingRepository->method('save')->willReturn($record);

        $this->userIdHasher->expects($this->never())->method('hash');
        $record->expects($this->never())->method('setUserId');

        $this->observer->execute($this->observerWithOrder($order));
    }

    public function testItDoesNotStoreAUserIdWhenUserIdTrackingIsDisabledForARegisteredCustomer(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getEntityId')->willReturn(55);
        $order->method('getCustomerId')->willReturn(42);

        $this->config->method('isTrackingActive')->with(1)->willReturn(true);
        $this->consentChecker->method('hasConsent')->with(1)->willReturn(true);
        $this->config->method('getSiteId')->with(1)->willReturn(3);
        $this->visitorIdResolver->method('resolve')->willReturn(null);
        $this->config->method('isUserIdTrackingEnabled')->with(1)->willReturn(false);

        $record = $this->createMock(OrderTracking::class);
        $this->orderTrackingFactory->method('create')->willReturn($record);
        $record->method('getEntityId')->willReturn('77');
        $this->orderTrackingRepository->method('save')->willReturn($record);

        $this->userIdHasher->expects($this->never())->method('hash');
        $record->expects($this->never())->method('setUserId');

        $this->observer->execute($this->observerWithOrder($order));
    }

    public function testItNeverThrowsFromTheObserverWhenSavingOrPublishingFails(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getEntityId')->willReturn(55);
        $order->method('getCustomerId')->willReturn(null);
        $order->method('getIncrementId')->willReturn('000000055');

        $this->config->method('isTrackingActive')->with(1)->willReturn(true);
        $this->consentChecker->method('hasConsent')->with(1)->willReturn(true);
        $this->config->method('getSiteId')->with(1)->willReturn(3);
        $this->visitorIdResolver->method('resolve')->willReturn('1234567890abcdef');

        $record = $this->createMock(OrderTracking::class);
        $this->orderTrackingFactory->method('create')->willReturn($record);

        $this->orderTrackingRepository->method('save')
            ->willThrowException(new \RuntimeException('duplicate order_id'));

        $this->publisher->expects($this->never())->method('publish');
        $this->logger->expects($this->once())->method('error');

        $this->observer->execute($this->observerWithOrder($order));
    }

    public function testItNeverThrowsFromTheObserverWhenPublishingFails(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getEntityId')->willReturn(55);
        $order->method('getCustomerId')->willReturn(null);
        $order->method('getIncrementId')->willReturn('000000055');

        $this->config->method('isTrackingActive')->with(1)->willReturn(true);
        $this->consentChecker->method('hasConsent')->with(1)->willReturn(true);
        $this->config->method('getSiteId')->with(1)->willReturn(3);
        $this->visitorIdResolver->method('resolve')->willReturn('1234567890abcdef');

        $record = $this->createMock(OrderTracking::class);
        $this->orderTrackingFactory->method('create')->willReturn($record);
        $record->method('getEntityId')->willReturn('77');
        $this->orderTrackingRepository->method('save')->willReturn($record);

        $this->publisher->method('publish')
            ->willThrowException(new \InvalidArgumentException('message not formed properly'));

        $this->logger->expects($this->once())->method('error');

        $this->observer->execute($this->observerWithOrder($order));
    }
}
