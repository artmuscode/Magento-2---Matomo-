<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Model;

use Artimus\Matomo\Api\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed access to the Matomo admin configuration.
 */
class Config implements ConfigInterface
{
    private const XML_PATH_ENABLED = 'artimus_matomo/general/enabled';

    private const XML_PATH_TRACKER_URL = 'artimus_matomo/general/tracker_url';

    private const XML_PATH_SERVER_TRACKER_URL = 'artimus_matomo/general/server_tracker_url';

    private const XML_PATH_SITE_ID = 'artimus_matomo/general/site_id';

    private const XML_PATH_AUTH_TOKEN = 'artimus_matomo/general/auth_token';

    private const XML_PATH_USER_ID_ENABLED = 'artimus_matomo/general/user_id_enabled';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     */
    public function __construct(ScopeConfigInterface $scopeConfig, EncryptorInterface $encryptor)
    {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    /**
     * @inheritDoc
     */
    public function isTrackingActive(?int $storeId = null): bool
    {
        $enabled = $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);

        return $enabled && $this->getTrackerUrl($storeId) !== '' && $this->getSiteId($storeId) > 0;
    }

    /**
     * @inheritDoc
     */
    public function isUserIdTrackingEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_USER_ID_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @inheritDoc
     */
    public function getTrackerUrl(?int $storeId = null): string
    {
        $trackerUrl = (string) $this->scopeConfig->getValue(
            self::XML_PATH_TRACKER_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $this->normalizeUrl($trackerUrl);
    }

    /**
     * Ensure a non-empty URL ends with a single trailing slash.
     *
     * @param string $url
     * @return string
     */
    private function normalizeUrl(string $url): string
    {
        return $url === '' ? '' : rtrim($url, '/') . '/';
    }

    /**
     * @inheritDoc
     */
    public function getServerTrackerUrl(?int $storeId = null): string
    {
        $serverTrackerUrl = (string) $this->scopeConfig->getValue(
            self::XML_PATH_SERVER_TRACKER_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $serverTrackerUrl === '' ? $this->getTrackerUrl($storeId) : $this->normalizeUrl($serverTrackerUrl);
    }

    /**
     * @inheritDoc
     */
    public function getSiteId(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(self::XML_PATH_SITE_ID, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getAuthToken(?int $storeId = null): string
    {
        $authToken = (string) $this->scopeConfig->getValue(
            self::XML_PATH_AUTH_TOKEN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $authToken === '' ? '' : $this->encryptor->decrypt($authToken);
    }
}
