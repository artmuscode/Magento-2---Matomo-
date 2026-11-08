<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit\Model;

use Artimus\Matomo\Model\VisitorIdResolver;
use Magento\Framework\App\Request\Http as Request;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class VisitorIdResolverTest extends TestCase
{
    /**
     * @var Request&MockObject
     */
    private $request;

    /**
     * @var VisitorIdResolver
     */
    private VisitorIdResolver $visitorIdResolver;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Request::class);
        $this->visitorIdResolver = new VisitorIdResolver($this->request);
    }

    public function testItResolvesTheVisitorIdFromTheRawCookieHeaderForTheConfiguredSiteId(): void
    {
        $this->request->method('getServer')
            ->with('HTTP_COOKIE')
            ->willReturn('_pk_id.1.1fff=1234567890abcdef.1700000000.1.;other_cookie=abc');

        $this->assertSame('1234567890abcdef', $this->visitorIdResolver->resolve(1));
    }

    public function testItResolvesTheVisitorIdWhenTheCookieNameUsesUnderscoresInsteadOfDots(): void
    {
        $this->request->method('getServer')
            ->with('HTTP_COOKIE')
            ->willReturn('_pk_id_1_1fff=1234567890abcdef.1700000000.1.;other_cookie=abc');

        $this->assertSame('1234567890abcdef', $this->visitorIdResolver->resolve(1));
    }

    public function testItIgnoresPkIdCookiesForOtherSiteIds(): void
    {
        $this->request->method('getServer')
            ->with('HTTP_COOKIE')
            ->willReturn('_pk_id.2.1fff=abcdef1234567890.1700000000.1.;');

        $this->assertNull($this->visitorIdResolver->resolve(1));
    }

    public function testItReturnsANullVisitorIdWhenTheCookieIsMissingOrMalformed(): void
    {
        $this->request->method('getServer')->with('HTTP_COOKIE')->willReturn(false);
        $this->assertNull($this->visitorIdResolver->resolve(1));

        $malformedRequest = $this->createMock(Request::class);
        $malformedRequest->method('getServer')
            ->with('HTTP_COOKIE')
            ->willReturn('_pk_id.1.1fff=not-hex-value.1700000000.1.;');
        $resolver = new VisitorIdResolver($malformedRequest);
        $this->assertNull($resolver->resolve(1));
    }
}
