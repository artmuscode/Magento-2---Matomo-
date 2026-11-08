<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit\Etc;

use PHPUnit\Framework\TestCase;

class ModuleXmlTest extends TestCase
{
    private const MODULE_XML_PATH = __DIR__ . '/../../../etc/module.xml';

    public function testItDeclaresModuleXmlWithNameArtimusMatomo(): void
    {
        $moduleXml = simplexml_load_file(self::MODULE_XML_PATH);

        $this->assertSame('Artimus_Matomo', (string) $moduleXml->module['name']);
    }

    public function testItDeclaresTheRequiredMagentoModulesInTheModuleSequence(): void
    {
        $moduleXml = simplexml_load_file(self::MODULE_XML_PATH);

        $sequencedModules = [];
        foreach ($moduleXml->module->sequence->module as $sequenceModule) {
            $sequencedModules[] = (string) $sequenceModule['name'];
        }

        $this->assertSame(
            [
                'Magento_Store',
                'Magento_Config',
                'Magento_Cookie',
                'Magento_Customer',
                'Magento_Checkout',
                'Magento_Sales',
                'Magento_Catalog',
                'Magento_CatalogSearch',
                'Magento_Csp',
                'Magento_MessageQueue',
                'Magento_MysqlMq',
            ],
            $sequencedModules
        );
    }
}
