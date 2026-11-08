<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Model;

use Artimus\Matomo\Api\Data\OrderTrackingSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

/**
 * Service data object with order tracking search results.
 */
class OrderTrackingSearchResults extends SearchResults implements OrderTrackingSearchResultsInterface
{
}
