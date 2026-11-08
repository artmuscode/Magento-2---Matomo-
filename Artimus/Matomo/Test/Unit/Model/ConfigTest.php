<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit\Model;

use Artimus\Matomo\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private const XML_PATH_ENABLED = 'artimus_matomo/general/enabled';
    private const XML_PATH_TRACKER_URL = 'artimus_matomo/general/tracker_url';
    private const XML_PATH_SERVER_TRACKER_URL = 'artimus_matomo/general/server_tracker_url';
    private const XML_PATH_SITE_ID = 'artimus_matomo/general/site_id';
    private const XML_PATH_AUTH_TOKEN = 'artimus_matomo/general/auth_token';
    private const XML_PATH_USER_ID_ENABLED = 'artimus_matomo/general/user_id_enabled';

    /**
     * @var ScopeConfigInterface&MockObject
     */
    private $scopeConfig;

    /**
     * @var EncryptorInterface&MockObject
     */
    private $encryptor;

    /**
     * @var Config
     */
    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->config = new Config($this->scopeConfig, $this->encryptor);
    }

    public function testItReportsTrackingActiveOnlyWhenEnabledAndTrackerUrlAndSiteIdAreSetForTheStore(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);
        $this->scopeConfig->method('getValue')
            ->willReturnMap(
                [
                    [self::XML_PATH_TRACKER_URL, ScopeInterface::SCOPE_STORE, null, 'https://matomo.test'],
                    [self::XML_PATH_SITE_ID, ScopeInterface::SCOPE_STORE, null, '1'],
                ]
            );

        $this->assertTrue($this->config->isTrackingActive());
    }

    public function testItReportsTrackingInactiveWhenTheSiteIdIsNotAPositiveInteger(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);
        $this->scopeConfig->method('getValue')
            ->willReturnMap(
                [
                    [self::XML_PATH_TRACKER_URL, ScopeInterface::SCOPE_STORE, null, 'https://matomo.test'],
                    [self::XML_PATH_SITE_ID, ScopeInterface::SCOPE_STORE, null, '0'],
                ]
            );

        $this->assertFalse($this->config->isTrackingActive());
    }

    public function testItReturnsTheTrackerUrlNormalisedWithATrailingSlash(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(self::XML_PATH_TRACKER_URL, ScopeInterface::SCOPE_STORE, null)
            ->willReturn('https://magento.test:8443');

        $this->assertSame('https://magento.test:8443/', $this->config->getTrackerUrl());
    }

    public function testItFallsBackToTheBrowserTrackerUrlWhenNoServerTrackerUrlIsConfigured(): void
    {
        $this->scopeConfig->method('getValue')
            ->willReturnMap(
                [
                    [self::XML_PATH_TRACKER_URL, ScopeInterface::SCOPE_STORE, null, 'https://magento.test:8443'],
                    [self::XML_PATH_SERVER_TRACKER_URL, ScopeInterface::SCOPE_STORE, null, ''],
                ]
            );

        $this->assertSame('https://magento.test:8443/', $this->config->getServerTrackerUrl());
    }

    public function testItReturnsTheDecryptedAuthToken(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(self::XML_PATH_AUTH_TOKEN, ScopeInterface::SCOPE_STORE, null)
            ->willReturn('encrypted-value');
        $this->encryptor->method('decrypt')
            ->with('encrypted-value')
            ->willReturn('plain-token');

        $this->assertSame('plain-token', $this->config->getAuthToken());
    }

    public function testItReadsEveryValueForTheRequestedStoreScope(): void
    {
        $storeId = 7;

        $this->scopeConfig->expects($this->exactly(2))
            ->method('isSetFlag')
            ->with($this->anything(), ScopeInterface::SCOPE_STORE, $storeId)
            ->willReturn(true);
        $this->scopeConfig->method('getValue')
            ->willReturnMap(
                [
                    [self::XML_PATH_TRACKER_URL, ScopeInterface::SCOPE_STORE, $storeId, 'https://magento.test:8443'],
                    [self::XML_PATH_SERVER_TRACKER_URL, ScopeInterface::SCOPE_STORE, $storeId, 'http://matomo'],
                    [self::XML_PATH_SITE_ID, ScopeInterface::SCOPE_STORE, $storeId, '3'],
                    [self::XML_PATH_AUTH_TOKEN, ScopeInterface::SCOPE_STORE, $storeId, 'encrypted-value'],
                ]
            );
        $this->encryptor->method('decrypt')->with('encrypted-value')->willReturn('plain-token');

        $this->assertTrue($this->config->isTrackingActive($storeId));
        $this->assertTrue($this->config->isUserIdTrackingEnabled($storeId));
        $this->assertSame('https://magento.test:8443/', $this->config->getTrackerUrl($storeId));
        $this->assertSame('http://matomo/', $this->config->getServerTrackerUrl($storeId));
        $this->assertSame(3, $this->config->getSiteId($storeId));
        $this->assertSame('plain-token', $this->config->getAuthToken($storeId));
    }

    public function testItReportsUserIdTrackingDisabledWhenTheSettingIsOff(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with(self::XML_PATH_USER_ID_ENABLED, ScopeInterface::SCOPE_STORE, null)
            ->willReturn(false);

        $this->assertFalse($this->config->isUserIdTrackingEnabled());
    }
}
