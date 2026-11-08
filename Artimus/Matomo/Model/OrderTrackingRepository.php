<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Model;

use Artimus\Matomo\Api\Data\OrderTrackingInterface;
use Artimus\Matomo\Api\Data\OrderTrackingSearchResultsInterfaceFactory;
use Artimus\Matomo\Api\OrderTrackingRepositoryInterface;
use Artimus\Matomo\Model\ResourceModel\OrderTracking as OrderTrackingResource;
use Artimus\Matomo\Model\ResourceModel\OrderTracking\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Order tracking record repository.
 */
class OrderTrackingRepository implements OrderTrackingRepositoryInterface
{
    /**
     * @var OrderTrackingResource
     */
    private $resource;

    /**
     * @var OrderTrackingFactory
     */
    private $orderTrackingFactory;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var OrderTrackingSearchResultsInterfaceFactory
     */
    private $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * @param OrderTrackingResource $resource
     * @param OrderTrackingFactory $orderTrackingFactory
     * @param CollectionFactory $collectionFactory
     * @param OrderTrackingSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        OrderTrackingResource $resource,
        OrderTrackingFactory $orderTrackingFactory,
        CollectionFactory $collectionFactory,
        OrderTrackingSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->orderTrackingFactory = $orderTrackingFactory;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @inheritDoc
     */
    public function save(OrderTrackingInterface $orderTracking)
    {
        try {
            $this->resource->save($orderTracking);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the order tracking record: %1', $exception->getMessage()),
                $exception
            );
        }

        return $orderTracking;
    }

    /**
     * @inheritDoc
     */
    public function getById($entityId)
    {
        $orderTracking = $this->orderTrackingFactory->create();
        $this->resource->load($orderTracking, $entityId);

        if (!$orderTracking->getId()) {
            throw new NoSuchEntityException(
                __('Order tracking record with id "%1" does not exist.', $entityId)
            );
        }

        return $orderTracking;
    }

    /**
     * @inheritDoc
     */
    public function getByOrderId($orderId)
    {
        $orderTracking = $this->orderTrackingFactory->create();
        $this->resource->load($orderTracking, $orderId, OrderTrackingInterface::ORDER_ID);

        if (!$orderTracking->getId()) {
            throw new NoSuchEntityException(
                __('Order tracking record for order id "%1" does not exist.', $orderId)
            );
        }

        return $orderTracking;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(OrderTrackingInterface $orderTracking)
    {
        try {
            $this->resource->delete($orderTracking);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the order tracking record: %1', $exception->getMessage()),
                $exception
            );
        }

        return true;
    }
}
