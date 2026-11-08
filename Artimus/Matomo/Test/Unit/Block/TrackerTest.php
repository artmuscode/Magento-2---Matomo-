<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit\Block;

use Artimus\Matomo\Api\ConfigInterface;
use Artimus\Matomo\Block\Tracker;
use Magento\Framework\View\Element\Template\Context;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class TrackerTest extends TestCase
{
    /**
     * @var Context&MockObject
     */
    private $context;

    /**
     * @var ConfigInterface&MockObject
     */
    private $config;

    /**
     * @var Tracker
     */
    private Tracker $block;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->block = new Tracker($this->context, $this->config);
    }

    public function testItRendersNoTrackerOutputWhenTrackingIsInactiveForTheStore(): void
    {
        $this->config->method('isTrackingActive')->willReturn(false);

        $toHtml = new ReflectionMethod($this->block, '_toHtml');
        $toHtml->setAccessible(true);

        $this->assertSame('', $toHtml->invoke($this->block));
    }
}
