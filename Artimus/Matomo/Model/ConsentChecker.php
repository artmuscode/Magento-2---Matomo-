<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Model;

use Artimus\Matomo\Api\ConsentCheckerInterface;
use Magento\Cookie\Helper\Cookie;
use Magento\Cookie\Helper\CookieFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Wraps Magento's Cookie Restriction Mode to answer consent questions.
 */
class ConsentChecker implements ConsentCheckerInterface
{
    /**
     * @var Cookie
     */
    private $cookieHelper;

    /**
     * @var CookieFactory
     */
    private $cookieHelperFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param Cookie $cookieHelper
     * @param CookieFactory $cookieHelperFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Cookie $cookieHelper,
        CookieFactory $cookieHelperFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->cookieHelper = $cookieHelper;
        $this->cookieHelperFactory = $cookieHelperFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritDoc
     */
    public function isConsentRequired(?int $storeId = null): bool
    {
        // Core returns the raw config value ("0"/"1"/null) despite the method name.
        return (bool) $this->getCookieHelper($storeId)->isCookieRestrictionModeEnabled();
    }

    /**
     * @inheritDoc
     */
    public function hasConsent(?int $storeId = null): bool
    {
        return !$this->getCookieHelper($storeId)->isUserNotAllowSaveCookie();
    }

    /**
     * Resolve the cookie helper for the given store, falling back to the injected (current store) helper.
     *
     * @param int|null $storeId
     * @return Cookie
     */
    private function getCookieHelper(?int $storeId): Cookie
    {
        if ($storeId === null) {
            return $this->cookieHelper;
        }

        $store = $this->storeManager->getStore($storeId);
        $website = $this->storeManager->getWebsite($store->getWebsiteId());

        return $this->cookieHelperFactory->create(
            ['data' => ['current_store' => $store, 'website' => $website]]
        );
    }
}
