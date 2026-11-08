<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Api;

/**
 * Answers whether a visitor may be tracked, based on Magento's Cookie Restriction Mode.
 */
interface ConsentCheckerInterface
{
    /**
     * Whether the visitor must explicitly consent before tracking starts for the given store.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isConsentRequired(?int $storeId = null): bool;

    /**
     * Whether the visitor has consented to tracking for the given store.
     *
     * True when cookie restriction mode is off, or the visitor has allowed cookies for the
     * store's website.
     *
     * This depends on the visitor's cookies, so it must never be called while rendering
     * cacheable HTML.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function hasConsent(?int $storeId = null): bool;
}
