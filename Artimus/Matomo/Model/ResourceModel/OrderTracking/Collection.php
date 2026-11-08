<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Model\ResourceModel\OrderTracking;

use Artimus\Matomo\Model\OrderTracking;
use Artimus\Matomo\Model\ResourceModel\OrderTracking as OrderTrackingResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Order tracking collection.
 */
class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(OrderTracking::class, OrderTrackingResource::class);
    }
}
