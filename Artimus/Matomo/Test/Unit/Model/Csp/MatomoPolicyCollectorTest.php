<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit\Model\Csp;

use Artimus\Matomo\Api\ConfigInterface;
use Artimus\Matomo\Model\Csp\MatomoPolicyCollector;
use Magento\Csp\Model\Policy\FetchPolicy;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MatomoPolicyCollectorTest extends TestCase
{
    /**
     * @var ConfigInterface&MockObject
     */
    private $config;

    /**
     * @var MatomoPolicyCollector
     */
    private MatomoPolicyCollector $collector;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->collector = new MatomoPolicyCollector($this->config);
    }

    public function testItAddsTheMatomoHostToScriptSrcConnectSrcAndImgSrcWhenTrackingIsActive(): void
    {
        $this->config->method('isTrackingActive')->willReturn(true);
        $this->config->method('getTrackerUrl')->willReturn('https://matomo.example.com/');

        $policies = $this->collector->collect([]);

        $policiesById = [];
        foreach ($policies as $policy) {
            $policiesById[$policy->getId()] = $policy;
        }

        foreach (['script-src', 'connect-src', 'img-src'] as $id) {
            $this->assertArrayHasKey($id, $policiesById);
            /** @var FetchPolicy $policy */
            $policy = $policiesById[$id];
            $this->assertContains('matomo.example.com', $policy->getHostSources());
        }
    }

    public function testItIncludesANonDefaultPortInTheAllowedHost(): void
    {
        $this->config->method('isTrackingActive')->willReturn(true);
        $this->config->method('getTrackerUrl')->willReturn('https://matomo.example.com:8443/');

        $policies = $this->collector->collect([]);

        /** @var FetchPolicy $policy */
        $policy = $policies[0];
        $this->assertContains('matomo.example.com:8443', $policy->getHostSources());
    }

    public function testItPreservesThePoliciesItReceives(): void
    {
        $this->config->method('isTrackingActive')->willReturn(true);
        $this->config->method('getTrackerUrl')->willReturn('https://matomo.example.com/');

        $existingPolicy = new FetchPolicy('font-src', false, ['fonts.example.com']);

        $policies = $this->collector->collect([$existingPolicy]);

        $this->assertContains($existingPolicy, $policies);
    }

    public function testItAddsNothingWhenTrackingIsInactive(): void
    {
        $this->config->method('isTrackingActive')->willReturn(false);
        $this->config->expects($this->never())->method('getTrackerUrl');

        $existingPolicy = new FetchPolicy('font-src', false, ['fonts.example.com']);

        $policies = $this->collector->collect([$existingPolicy]);

        $this->assertSame([$existingPolicy], $policies);
    }

    public function testItAddsNothingWhenTheTrackerUrlIsInvalid(): void
    {
        $this->config->method('isTrackingActive')->willReturn(true);
        $this->config->method('getTrackerUrl')->willReturn('://not-a-valid-url');

        $existingPolicy = new FetchPolicy('font-src', false, ['fonts.example.com']);

        $policies = $this->collector->collect([$existingPolicy]);

        $this->assertSame([$existingPolicy], $policies);
    }
}
