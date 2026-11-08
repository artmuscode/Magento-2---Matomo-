<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit\CustomerData;

use Artimus\Matomo\Api\ConfigInterface;
use Artimus\Matomo\Api\UserIdHasherInterface;
use Artimus\Matomo\CustomerData\MatomoSection;
use Magento\Customer\Model\Session;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MatomoSectionTest extends TestCase
{
    private const HASH = 'abc123hashvalue';

    /** @var Session&MockObject */
    private $session;

    /** @var ConfigInterface&MockObject */
    private $config;

    /** @var UserIdHasherInterface&MockObject */
    private $hasher;

    protected function setUp(): void
    {
        $this->session = $this->createMock(Session::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->hasher = $this->createMock(UserIdHasherInterface::class);
    }

    private function section(): MatomoSection
    {
        return new MatomoSection($this->session, $this->config, $this->hasher);
    }

    private function loggedIn(string $customerId = '42', bool $enabled = true): void
    {
        $this->session->method('isLoggedIn')->willReturn(true);
        // Core returns the raw session value, which is a string.
        $this->session->method('getCustomerId')->willReturn($customerId);
        $this->config->method('isUserIdTrackingEnabled')->willReturn($enabled);
    }

    public function testItReturnsAHashedUserIdForALoggedInCustomerWhenUserIdTrackingIsEnabled(): void
    {
        $this->loggedIn();
        $this->hasher->method('hash')->willReturn(self::HASH);

        $this->assertSame(['userId' => self::HASH], $this->section()->getSectionData());
    }

    public function testItReturnsAnEmptySectionForAGuest(): void
    {
        $this->session->method('isLoggedIn')->willReturn(false);
        $this->config->method('isUserIdTrackingEnabled')->willReturn(true);
        $this->hasher->expects($this->never())->method('hash');

        $this->assertSame([], $this->section()->getSectionData());
    }

    public function testItReturnsAnEmptySectionWhenUserIdTrackingIsDisabled(): void
    {
        $this->loggedIn('42', false);
        $this->hasher->expects($this->never())->method('hash');

        $this->assertSame([], $this->section()->getSectionData());
    }

    public function testItReturnsTheHashersValueForTheLoggedInCustomerId(): void
    {
        $this->loggedIn('42');
        $this->hasher->expects($this->once())->method('hash')->with(42)->willReturn('xyz');

        $this->assertSame(['userId' => 'xyz'], $this->section()->getSectionData());
    }

    public function testItNeverIncludesTheRawCustomerIdOrEmailInTheSectionData(): void
    {
        $this->loggedIn('987654');
        $this->hasher->method('hash')->willReturn(self::HASH);

        $data = $this->section()->getSectionData();

        $this->assertSame(['userId'], array_keys($data));
        $this->assertStringNotContainsString('987654', (string)json_encode($data));
    }
}
