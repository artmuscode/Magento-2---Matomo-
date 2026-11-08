<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Api;

/**
 * Typed access to the Matomo admin configuration (Stores > Configuration > Sales > Matomo Analytics).
 *
 * @api
 */
interface ConfigInterface
{
    /**
     * Whether tracking is active: enabled, tracker URL configured and a valid site ID set.
     *
     * @param int|null $storeId Null uses the current store.
     * @return bool
     */
    public function isTrackingActive(?int $storeId = null): bool;

    /**
     * Whether the Matomo User ID feature is enabled for logged-in customers.
     *
     * @param int|null $storeId Null uses the current store.
     * @return bool
     */
    public function isUserIdTrackingEnabled(?int $storeId = null): bool;

    /**
     * The browser-facing tracker base URL, normalised with a trailing slash.
     *
     * @param int|null $storeId Null uses the current store.
     * @return string
     */
    public function getTrackerUrl(?int $storeId = null): string;

    /**
     * The tracker base URL used by server-side (PHP SDK) calls.
     *
     * Falls back to the browser tracker URL when no internal override is configured.
     *
     * @param int|null $storeId Null uses the current store.
     * @return string
     */
    public function getServerTrackerUrl(?int $storeId = null): string;

    /**
     * The configured Matomo site ID.
     *
     * @param int|null $storeId Null uses the current store.
     * @return int
     */
    public function getSiteId(?int $storeId = null): int;

    /**
     * The decrypted Matomo API auth token.
     *
     * @param int|null $storeId Null uses the current store.
     * @return string
     */
    public function getAuthToken(?int $storeId = null): string;
}
