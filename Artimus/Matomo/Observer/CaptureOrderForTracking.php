<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Observer;

use Artimus\Matomo\Api\ConfigInterface;
use Artimus\Matomo\Api\ConsentCheckerInterface;
use Artimus\Matomo\Api\Data\OrderTrackingInterface;
use Artimus\Matomo\Api\OrderTrackingRepositoryInterface;
use Artimus\Matomo\Api\UserIdHasherInterface;
use Artimus\Matomo\Model\OrderTrackingFactory;
use Artimus\Matomo\Model\VisitorIdResolver;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Captures the Matomo visitor ID and consent for a placed order and queues it for server-side
 * ecommerce order tracking.
 *
 * Registered on `checkout_submit_all_after` in the frontend, webapi_rest and graphql areas only
 * (admin-created orders are not tracked). Never throws into checkout: every failure is caught
 * and logged.
 */
class CaptureOrderForTracking implements ObserverInterface
{
    private const TOPIC_ORDER_TRACK = 'artimus.matomo.order.track';

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var ConsentCheckerInterface
     */
    private $consentChecker;

    /**
     * @var VisitorIdResolver
     */
    private $visitorIdResolver;

    /**
     * @var UserIdHasherInterface
     */
    private $userIdHasher;

    /**
     * @var OrderTrackingFactory
     */
    private $orderTrackingFactory;

    /**
     * @var OrderTrackingRepositoryInterface
     */
    private $orderTrackingRepository;

    /**
     * @var PublisherInterface
     */
    private $publisher;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ConfigInterface $config
     * @param ConsentCheckerInterface $consentChecker
     * @param VisitorIdResolver $visitorIdResolver
     * @param UserIdHasherInterface $userIdHasher
     * @param OrderTrackingFactory $orderTrackingFactory
     * @param OrderTrackingRepositoryInterface $orderTrackingRepository
     * @param PublisherInterface $publisher
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConfigInterface $config,
        ConsentCheckerInterface $consentChecker,
        VisitorIdResolver $visitorIdResolver,
        UserIdHasherInterface $userIdHasher,
        OrderTrackingFactory $orderTrackingFactory,
        OrderTrackingRepositoryInterface $orderTrackingRepository,
        PublisherInterface $publisher,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->consentChecker = $consentChecker;
        $this->visitorIdResolver = $visitorIdResolver;
        $this->userIdHasher = $userIdHasher;
        $this->orderTrackingFactory = $orderTrackingFactory;
        $this->orderTrackingRepository = $orderTrackingRepository;
        $this->publisher = $publisher;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function execute(EventObserver $observer)
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order instanceof Order) {
            return;
        }

        try {
            $this->captureOrder($order);
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf(
                    'Artimus_Matomo: failed to capture order %s for tracking: %s',
                    (string) $order->getIncrementId(),
                    $exception->getMessage()
                ),
                ['exception' => $exception]
            );
        }
    }

    /**
     * Save the order tracking record and publish it for server-side tracking.
     *
     * @param Order $order
     * @return void
     */
    private function captureOrder(Order $order): void
    {
        $storeId = (int) $order->getStoreId();
        if (!$this->config->isTrackingActive($storeId)) {
            return;
        }

        $record = $this->orderTrackingFactory->create();
        $record->setOrderId((int) $order->getEntityId());
        $record->setStoreId($storeId);

        if (!$this->consentChecker->hasConsent($storeId)) {
            $record->setStatus(OrderTrackingInterface::STATUS_SKIPPED);
            $this->orderTrackingRepository->save($record);
            return;
        }

        $record->setVisitorId($this->visitorIdResolver->resolve($this->config->getSiteId($storeId)));

        $customerId = $order->getCustomerId();
        if ($customerId !== null && $this->config->isUserIdTrackingEnabled($storeId)) {
            $record->setUserId($this->userIdHasher->hash((int) $customerId));
        }

        $record->setStatus(OrderTrackingInterface::STATUS_PENDING);
        $savedRecord = $this->orderTrackingRepository->save($record);

        $this->publisher->publish(self::TOPIC_ORDER_TRACK, (int) $savedRecord->getEntityId());
    }
}
