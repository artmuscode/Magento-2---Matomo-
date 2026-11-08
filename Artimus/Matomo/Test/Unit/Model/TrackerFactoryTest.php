<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit\Model;

use Artimus\Matomo\Model\TrackerFactory;
use PHPUnit\Framework\TestCase;

class TrackerFactoryTest extends TestCase
{
    public function testItCreatesAMatomoTrackerWithCookieSupportDisabled(): void
    {
        $factory = new TrackerFactory();

        $tracker = $factory->create(3, 'https://matomo.test/');

        $this->assertInstanceOf(\MatomoTracker::class, $tracker);

        $property = new \ReflectionProperty(\MatomoTracker::class, 'configCookiesDisabled');
        $property->setAccessible(true);
        $this->assertTrue($property->getValue($tracker));
    }
}
