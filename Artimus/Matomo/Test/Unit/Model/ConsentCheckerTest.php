<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit\Model;

use Artimus\Matomo\Model\ConsentChecker;
use Magento\Cookie\Helper\Cookie;
use Magento\Cookie\Helper\CookieFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConsentCheckerTest extends TestCase
{
    /**
     * @var Cookie&MockObject
     */
    private $cookieHelper;

    /**
     * @var CookieFactory&MockObject
     */
    private $cookieHelperFactory;

    /**
     * @var StoreManagerInterface&MockObject
     */
    private $storeManager;

    /**
     * @var ConsentChecker
     */
    private ConsentChecker $consentChecker;

    protected function setUp(): void
    {
        $this->cookieHelper = $this->createMock(Cookie::class);
        $this->cookieHelperFactory = $this->createMock(CookieFactory::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->consentChecker = new ConsentChecker(
            $this->cookieHelper,
            $this->cookieHelperFactory,
            $this->storeManager
        );
    }

    /**
     * Core's helper returns the raw config value ("0"/"1"/null), not a bool.
     *
     * @return array
     */
    public static function rawRestrictionModeValues(): array
    {
        return [
            'disabled' => ['0', false],
            'enabled' => ['1', true],
            'never configured' => [null, false],
        ];
    }

    /**
     * @dataProvider rawRestrictionModeValues
     * @param string|null $rawValue
     * @param bool $expected
     */
    public function testItReturnsABoolForTheRawConfigValueCoreReturns(?string $rawValue, bool $expected): void
    {
        $this->cookieHelper->method('isCookieRestrictionModeEnabled')->willReturn($rawValue);

        $this->assertSame($expected, $this->consentChecker->isConsentRequired());
    }

    public function testItDoesNotRequireConsentWhenCookieRestrictionModeIsDisabled(): void
    {
        $this->cookieHelper->method('isCookieRestrictionModeEnabled')->willReturn('0');

        $this->assertFalse($this->consentChecker->isConsentRequired());
    }

    public function testItRequiresConsentWhenCookieRestrictionModeIsEnabled(): void
    {
        $this->cookieHelper->method('isCookieRestrictionModeEnabled')->willReturn('1');

        $this->assertTrue($this->consentChecker->isConsentRequired());
    }

    public function testItGrantsConsentWhenCookieRestrictionModeIsDisabled(): void
    {
        $this->cookieHelper->method('isUserNotAllowSaveCookie')->willReturn(false);

        $this->assertTrue($this->consentChecker->hasConsent());
    }

    public function testItGrantsConsentWhenRestrictionModeIsOnAndTheVisitorAllowedCookiesForTheCurrentWebsite(): void
    {
        $this->cookieHelper->method('isUserNotAllowSaveCookie')->willReturn(false);

        $this->assertTrue($this->consentChecker->hasConsent());
    }

    public function testItDeniesConsentWhenRestrictionModeIsEnabledAndTheVisitorHasNotAllowedCookies(): void
    {
        $this->cookieHelper->method('isUserNotAllowSaveCookie')->willReturn(true);

        $this->assertFalse($this->consentChecker->hasConsent());
    }

    public function testItEvaluatesConsentAgainstTheGivenStoresWebsiteWhenAStoreIdIsPassed(): void
    {
        $storeId = 5;
        $websiteId = 2;
        $store = $this->createMock(StoreInterface::class);
        $website = $this->createMock(WebsiteInterface::class);

        $this->storeManager->method('getStore')->with($storeId)->willReturn($store);
        $store->method('getWebsiteId')->willReturn($websiteId);
        $this->storeManager->method('getWebsite')->with($websiteId)->willReturn($website);

        $storeCookieHelper = $this->createMock(Cookie::class);
        $this->cookieHelperFactory->method('create')
            ->with(['data' => ['current_store' => $store, 'website' => $website]])
            ->willReturn($storeCookieHelper);
        $storeCookieHelper->method('isUserNotAllowSaveCookie')->willReturn(false);

        $this->cookieHelper->expects($this->never())->method('isUserNotAllowSaveCookie');

        $this->assertTrue($this->consentChecker->hasConsent($storeId));
    }
}
