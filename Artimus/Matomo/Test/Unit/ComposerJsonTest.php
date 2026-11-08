<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the module's own manifest.
 *
 * Composer never reads a composer.json under app/code, so this file is a declaration rather than
 * an installer. The module's third-party runtime dependency (matomo/matomo-php-tracker) is
 * installed at the project root, guarded separately by ComposerDependencyTest.
 */
class ComposerJsonTest extends TestCase
{
    private const COMPOSER_JSON_PATH = __DIR__ . '/../../composer.json';

    private const MODULE_XML_PATH = __DIR__ . '/../../etc/module.xml';

    public function testItShipsValidJsonAtTheModuleRoot(): void
    {
        $this->assertIsArray($this->composerJson());
    }

    public function testItDeclaresItselfAsAMagento2Module(): void
    {
        $composerJson = $this->composerJson();

        $this->assertSame('artimus/module-matomo', $composerJson['name']);
        $this->assertSame('magento2-module', $composerJson['type']);
    }

    public function testItRegistersTheModuleAndItsNamespaceForAutoloading(): void
    {
        $autoload = $this->composerJson()['autoload'];

        $this->assertContains('registration.php', $autoload['files']);
        $this->assertSame('', $autoload['psr-4']['Artimus\\Matomo\\']);
    }

    public function testItRequiresAComposerPackageForEveryModuleItSequences(): void
    {
        $require = $this->composerJson()['require'];
        $moduleXml = simplexml_load_file(self::MODULE_XML_PATH);

        foreach ($moduleXml->module->sequence->module as $sequenceModule) {
            $package = 'magento/module-' . $this->toPackageSuffix((string) $sequenceModule['name']);

            $this->assertArrayHasKey($package, $require);
        }
    }

    public function testItRequiresTheMatomoPhpTrackerSdk(): void
    {
        $require = $this->composerJson()['require'];

        $this->assertArrayHasKey('matomo/matomo-php-tracker', $require);
    }

    /**
     * Convert a Magento module name to its Composer package suffix.
     *
     * @param string $moduleName
     * @return string
     */
    private function toPackageSuffix(string $moduleName): string
    {
        $withoutVendor = str_replace('Magento_', '', $moduleName);

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $withoutVendor));
    }

    /**
     * Decode the module's composer.json.
     *
     * @return array
     */
    private function composerJson(): array
    {
        return json_decode((string) file_get_contents(self::COMPOSER_JSON_PATH), true);
    }
}
