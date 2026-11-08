<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Api;

use Artimus\Matomo\Api\Data\OrderTrackingInterface;
use Artimus\Matomo\Api\Data\OrderTrackingSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Order tracking record repository.
 *
 * @api
 */
interface OrderTrackingRepositoryInterface
{
    /**
     * Save an order tracking record.
     *
     * Saving a second record for an order ID that is already tracked fails on the table's unique
     * constraint, which is what gives idempotency: each order is tracked at most once.
     *
     * @param OrderTrackingInterface $orderTracking
     * @return OrderTrackingInterface
     * @throws CouldNotSaveException
     */
    public function save(OrderTrackingInterface $orderTracking);

    /**
     * Load an order tracking record by its entity ID.
     *
     * @param int $entityId
     * @return OrderTrackingInterface
     * @throws NoSuchEntityException
     */
    public function getById($entityId);

    /**
     * Load an order tracking record by the order ID it tracks.
     *
     * @param int $orderId
     * @return OrderTrackingInterface
     * @throws NoSuchEntityException
     */
    public function getByOrderId($orderId);

    /**
     * List order tracking records matching the given search criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return OrderTrackingSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * Delete an order tracking record.
     *
     * @param OrderTrackingInterface $orderTracking
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(OrderTrackingInterface $orderTracking);
}
