<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit\Etc;

use PHPUnit\Framework\TestCase;

class EventsXmlTest extends TestCase
{
    private const MODULE_ETC_PATH = __DIR__ . '/../../../etc';

    private const EVENT_NAME = 'checkout_submit_all_after';

    private const OBSERVER_INSTANCE = 'Artimus\Matomo\Observer\CaptureOrderForTracking';

    public function testItRegistersTheOrderObserverOnlyInTheFrontendWebapiRestAndGraphqlAreas(): void
    {
        $areasThatMustRegisterIt = ['frontend', 'webapi_rest', 'graphql'];
        foreach ($areasThatMustRegisterIt as $area) {
            $this->assertTrue(
                $this->areaRegistersObserver($area),
                sprintf('Expected area "%s" to register the observer on "%s".', $area, self::EVENT_NAME)
            );
        }

        $areasThatMustNotRegisterIt = ['adminhtml'];
        foreach ($areasThatMustNotRegisterIt as $area) {
            $this->assertFalse(
                $this->areaRegistersObserver($area),
                sprintf('Expected area "%s" to NOT register the observer on "%s".', $area, self::EVENT_NAME)
            );
        }

        $this->assertFalse(
            $this->globalEventsXmlRegistersObserver(),
            'Expected the global etc/events.xml to NOT register the observer.'
        );
    }

    private function areaRegistersObserver(string $area): bool
    {
        $path = self::MODULE_ETC_PATH . '/' . $area . '/events.xml';
        if (!is_file($path)) {
            return false;
        }

        return $this->eventsXmlRegistersObserver($path);
    }

    private function globalEventsXmlRegistersObserver(): bool
    {
        $path = self::MODULE_ETC_PATH . '/events.xml';
        if (!is_file($path)) {
            return false;
        }

        return $this->eventsXmlRegistersObserver($path);
    }

    private function eventsXmlRegistersObserver(string $path): bool
    {
        $eventsXml = simplexml_load_file($path);

        foreach ($eventsXml->event as $event) {
            if ((string) $event['name'] !== self::EVENT_NAME) {
                continue;
            }

            foreach ($event->observer as $observer) {
                if ((string) $observer['instance'] === self::OBSERVER_INSTANCE) {
                    return true;
                }
            }
        }

        return false;
    }
}
