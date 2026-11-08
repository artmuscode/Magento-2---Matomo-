<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Order tracking search results.
 *
 * @api
 */
interface OrderTrackingSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get order tracking items.
     *
     * @return \Artimus\Matomo\Api\Data\OrderTrackingInterface[]
     */
    public function getItems();

    /**
     * Set order tracking items.
     *
     * @param \Artimus\Matomo\Api\Data\OrderTrackingInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
