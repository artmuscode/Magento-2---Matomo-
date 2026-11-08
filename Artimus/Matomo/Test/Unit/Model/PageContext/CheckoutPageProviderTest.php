<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit\Model\PageContext;

use Artimus\Matomo\Model\PageContext\CheckoutPageProvider;
use Magento\Framework\App\Request\Http;
use PHPUnit\Framework\TestCase;

class CheckoutPageProviderTest extends TestCase
{
    /**
     * @param string $actionName
     * @return CheckoutPageProvider
     */
    private function createProvider(string $actionName): CheckoutPageProvider
    {
        $request = $this->createMock(Http::class);
        $request->method('getFullActionName')->willReturn($actionName);

        return new CheckoutPageProvider($request);
    }

    public function testItReplacesTheDefaultPageViewOnTheCheckoutPage(): void
    {
        $provider = $this->createProvider('checkout_index_index');

        $this->assertTrue($provider->replacesPageView());
        $this->assertSame([], $provider->getCommands());
    }

    public function testItDoesNotReplaceThePageViewOutsideTheCheckoutPage(): void
    {
        foreach (['checkout_cart_index', 'cms_index_index', 'checkout_onepage_success'] as $action) {
            $this->assertFalse($this->createProvider($action)->replacesPageView());
            $this->assertSame([], $this->createProvider($action)->getCommands());
        }
    }
}
