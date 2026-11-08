<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit;

use Magento\Framework\Component\ComponentRegistrar;
use PHPUnit\Framework\TestCase;

class RegistrationTest extends TestCase
{
    public function testItRegistersTheArtimusMatomoModuleViaRegistrationPhp(): void
    {
        // registration.php is auto-loaded for all app/code modules via
        // app/etc/NonComposerComponentRegistration.php during bootstrap.
        $paths = (new ComponentRegistrar())->getPaths(ComponentRegistrar::MODULE);

        $this->assertArrayHasKey('Artimus_Matomo', $paths);
        $this->assertSame(
            realpath(__DIR__ . '/../..'),
            realpath($paths['Artimus_Matomo'])
        );
    }
}
