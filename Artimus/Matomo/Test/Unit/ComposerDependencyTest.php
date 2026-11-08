<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit;

use PHPUnit\Framework\TestCase;

class ComposerDependencyTest extends TestCase
{
    public function testItListsMatomoMatomoPhpTrackerAsAComposerDependency(): void
    {
        $composerJson = json_decode(
            (string) file_get_contents(__DIR__ . '/../../../../../../composer.json'),
            true
        );

        $this->assertArrayHasKey('matomo/matomo-php-tracker', $composerJson['require']);
    }
}
