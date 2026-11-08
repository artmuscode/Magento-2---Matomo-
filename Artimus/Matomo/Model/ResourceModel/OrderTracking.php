<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Order tracking resource model.
 */
class OrderTracking extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('artimus_matomo_order_tracking', 'entity_id');
    }
}
