<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Model;

use Artimus\Matomo\Api\Data\OrderTrackingInterface;
use Artimus\Matomo\Model\ResourceModel\OrderTracking as OrderTrackingResource;
use Magento\Framework\Model\AbstractModel;

/**
 * Order tracking record.
 */
class OrderTracking extends AbstractModel implements OrderTrackingInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(OrderTrackingResource::class);
        $this->setData(self::STATUS, self::STATUS_PENDING);
        $this->setData(self::ATTEMPTS, 0);
    }

    /**
     * @inheritDoc
     */
    public function getEntityId()
    {
        return $this->getData(self::ENTITY_ID);
    }

    /**
     * @inheritDoc
     */
    public function setEntityId($entityId)
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    /**
     * @inheritDoc
     */
    public function getOrderId()
    {
        return $this->getData(self::ORDER_ID);
    }

    /**
     * @inheritDoc
     */
    public function setOrderId($orderId)
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * @inheritDoc
     */
    public function getStoreId()
    {
        return $this->getData(self::STORE_ID);
    }

    /**
     * @inheritDoc
     */
    public function setStoreId($storeId)
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getVisitorId()
    {
        return $this->getData(self::VISITOR_ID);
    }

    /**
     * @inheritDoc
     */
    public function setVisitorId($visitorId)
    {
        return $this->setData(self::VISITOR_ID, $visitorId);
    }

    /**
     * @inheritDoc
     */
    public function getUserId()
    {
        return $this->getData(self::USER_ID);
    }

    /**
     * @inheritDoc
     */
    public function setUserId($userId)
    {
        return $this->setData(self::USER_ID, $userId);
    }

    /**
     * @inheritDoc
     */
    public function getStatus()
    {
        return $this->getData(self::STATUS);
    }

    /**
     * @inheritDoc
     */
    public function setStatus($status)
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritDoc
     */
    public function getAttempts()
    {
        return $this->getData(self::ATTEMPTS);
    }

    /**
     * @inheritDoc
     */
    public function setAttempts($attempts)
    {
        return $this->setData(self::ATTEMPTS, $attempts);
    }

    /**
     * @inheritDoc
     */
    public function getLastError()
    {
        return $this->getData(self::LAST_ERROR);
    }

    /**
     * @inheritDoc
     */
    public function setLastError($lastError)
    {
        return $this->setData(self::LAST_ERROR, $lastError);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function getSentAt()
    {
        return $this->getData(self::SENT_AT);
    }

    /**
     * @inheritDoc
     */
    public function setSentAt($sentAt)
    {
        return $this->setData(self::SENT_AT, $sentAt);
    }
}
