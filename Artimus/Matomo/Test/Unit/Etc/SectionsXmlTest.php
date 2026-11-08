<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit\Etc;

use PHPUnit\Framework\TestCase;

class SectionsXmlTest extends TestCase
{
    private const SECTIONS_XML = __DIR__ . '/../../../etc/frontend/sections.xml';

    public function testItDeclaresTheMatomoSectionForTheCustomerAjaxLoginAction(): void
    {
        $this->assertContains('matomo', $this->sectionsFor('customer/ajax/login'));
    }

    public function testItKeepsTheMatomoSectionDeclaredForLoginPostLogoutAndCreatepost(): void
    {
        foreach (['customer/account/loginPost', 'customer/account/logout', 'customer/account/createpost'] as $action) {
            $this->assertContains('matomo', $this->sectionsFor($action), $action);
        }
    }

    /**
     * @return string[]
     */
    private function sectionsFor(string $actionName): array
    {
        $xml = simplexml_load_file(self::SECTIONS_XML);
        $sections = [];
        foreach ($xml->action as $action) {
            if ((string) $action['name'] !== $actionName) {
                continue;
            }
            foreach ($action->section as $section) {
                $sections[] = (string) $section['name'];
            }
        }

        return $sections;
    }
}
