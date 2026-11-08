<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Api\Data;

/**
 * One tracking record per order: the Matomo visitor ID captured at placement, the send status
 * and the attempt count.
 *
 * @api
 */
interface OrderTrackingInterface
{
    /**#@+
     * Constants for keys of data array. Identical to the name of the getter in snake case.
     */
    public const ENTITY_ID = 'entity_id';
    public const ORDER_ID = 'order_id';
    public const STORE_ID = 'store_id';
    public const VISITOR_ID = 'visitor_id';
    public const USER_ID = 'user_id';
    public const STATUS = 'status';
    public const ATTEMPTS = 'attempts';
    public const LAST_ERROR = 'last_error';
    public const CREATED_AT = 'created_at';
    public const SENT_AT = 'sent_at';
    /**#@-*/

    /**#@+
     * Send status values.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_ABANDONED = 'abandoned';
    /**#@-*/

    /**
     * Maximum number of send attempts before a record is abandoned.
     */
    public const MAX_ATTEMPTS = 10;

    /**
     * Get entity ID.
     *
     * @return int|null
     */
    public function getEntityId();

    /**
     * Set entity ID.
     *
     * @param int $entityId
     * @return $this
     */
    public function setEntityId($entityId);

    /**
     * Get the tracked order ID.
     *
     * @return int|null
     */
    public function getOrderId();

    /**
     * Set the tracked order ID.
     *
     * @param int $orderId
     * @return $this
     */
    public function setOrderId($orderId);

    /**
     * Get the store ID the order was placed in.
     *
     * @return int|null
     */
    public function getStoreId();

    /**
     * Set the store ID the order was placed in.
     *
     * @param int $storeId
     * @return $this
     */
    public function setStoreId($storeId);

    /**
     * Get the Matomo visitor ID captured at placement.
     *
     * @return string|null
     */
    public function getVisitorId();

    /**
     * Set the Matomo visitor ID captured at placement.
     *
     * @param string|null $visitorId
     * @return $this
     */
    public function setVisitorId($visitorId);

    /**
     * Get the hashed Matomo User ID.
     *
     * @return string|null
     */
    public function getUserId();

    /**
     * Set the hashed Matomo User ID.
     *
     * @param string|null $userId
     * @return $this
     */
    public function setUserId($userId);

    /**
     * Get the send status.
     *
     * @return string
     */
    public function getStatus();

    /**
     * Set the send status.
     *
     * @param string $status
     * @return $this
     */
    public function setStatus($status);

    /**
     * Get the number of send attempts.
     *
     * @return int
     */
    public function getAttempts();

    /**
     * Set the number of send attempts.
     *
     * @param int $attempts
     * @return $this
     */
    public function setAttempts($attempts);

    /**
     * Get the last error message, if any.
     *
     * @return string|null
     */
    public function getLastError();

    /**
     * Set the last error message.
     *
     * @param string|null $lastError
     * @return $this
     */
    public function setLastError($lastError);

    /**
     * Get the creation timestamp.
     *
     * @return string|null
     */
    public function getCreatedAt();

    /**
     * Set the creation timestamp.
     *
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt);

    /**
     * Get the timestamp the order was successfully sent to Matomo.
     *
     * @return string|null
     */
    public function getSentAt();

    /**
     * Set the timestamp the order was successfully sent to Matomo.
     *
     * @param string|null $sentAt
     * @return $this
     */
    public function setSentAt($sentAt);
}
