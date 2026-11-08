<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit\Model;

use Artimus\Matomo\Model\UserIdHasher;
use Magento\Framework\App\DeploymentConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UserIdHasherTest extends TestCase
{
    /**
     * @var DeploymentConfig&MockObject
     */
    private $deploymentConfig;

    /**
     * @var UserIdHasher
     */
    private UserIdHasher $userIdHasher;

    protected function setUp(): void
    {
        $this->deploymentConfig = $this->createMock(DeploymentConfig::class);
        $this->userIdHasher = new UserIdHasher($this->deploymentConfig);
    }

    public function testItProducesTheSame64HexCharHashForTheSameCustomerId(): void
    {
        $this->deploymentConfig->method('get')->with('crypt/key')->willReturn('the-salt');

        $first = $this->userIdHasher->hash(42);
        $second = $this->userIdHasher->hash(42);

        $this->assertSame($first, $second);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first);
    }

    public function testItProducesDifferentHashesForDifferentCustomerIds(): void
    {
        $this->deploymentConfig->method('get')->with('crypt/key')->willReturn('the-salt');

        $this->assertNotSame($this->userIdHasher->hash(42), $this->userIdHasher->hash(43));
    }

    public function testItUsesOnlyTheFirstCryptKeyAsTheSaltWhenSeveralKeysAreConfigured(): void
    {
        $this->deploymentConfig->method('get')->with('crypt/key')->willReturn('key-one');
        $singleKeyHash = $this->userIdHasher->hash(42);

        $this->deploymentConfig = $this->createMock(DeploymentConfig::class);
        $this->deploymentConfig->method('get')->with('crypt/key')->willReturn("key-one\nkey-two");
        $this->userIdHasher = new UserIdHasher($this->deploymentConfig);
        $multiKeyHash = $this->userIdHasher->hash(42);

        $this->assertSame($singleKeyHash, $multiKeyHash);
    }
}
