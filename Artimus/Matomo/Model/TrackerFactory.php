<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Model;

/**
 * Wraps construction of the global MatomoTracker SDK class, the only place `new` is used, so
 * tests can mock the SDK through this factory instead.
 */
class TrackerFactory
{
    /**
     * Build a MatomoTracker instance configured for server-side (CLI) use.
     *
     * Cookie support is disabled immediately after construction: the SDK's constructor and its
     * per-request methods read/write $_COOKIE, which is meaningless (and must not leak state)
     * in a queue consumer with no real HTTP request/response cycle.
     *
     * @param int $siteId
     * @param string $apiUrl
     * @return \MatomoTracker
     */
    public function create(int $siteId, string $apiUrl): \MatomoTracker
    {
        $tracker = new \MatomoTracker($siteId, $apiUrl);
        $tracker->disableCookieSupport();

        return $tracker;
    }
}
