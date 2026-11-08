<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Api;

/**
 * Adds page-specific `_paq` commands to the tracker bootstrap (e.g. ecommerce view, site search).
 *
 * Implementations are injected into the `Artimus\Matomo\Model\PageContextPool` as a di.xml array
 * argument and must only read store/catalog-level data, since their output is rendered into
 * full-page-cached HTML.
 *
 * @api
 */
interface PageContextProviderInterface
{
    /**
     * The ordered list of `_paq` commands this provider contributes, each an array like
     * `['setEcommerceView', $sku, $name, $categories, $price]`.
     *
     * @return array
     */
    public function getCommands(): array;

    /**
     * Whether this provider's commands already send a page view.
     *
     * If so, the tracker must not also push the default `trackPageView` command (e.g. site search
     * sends `trackSiteSearch` instead).
     *
     * @return bool
     */
    public function replacesPageView(): bool;
}
