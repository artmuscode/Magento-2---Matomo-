<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\PhpEnvironment\Request as PhpEnvironmentRequest;

/**
 * Resolves the Matomo visitor ID from the raw `Cookie` request header.
 *
 * `Magento\Framework\HTTP\PhpEnvironment\Request::getCookie()` without a name always returns
 * null, and PHP mangles dots to underscores in `$_COOKIE` keys, so the `_pk_id.{siteId}.*`
 * cookie is read from the raw header instead.
 */
class VisitorIdResolver
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @param RequestInterface $request
     */
    public function __construct(RequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     * Resolve the Matomo visitor ID for the given site ID from the raw cookie header.
     *
     * @param int $siteId
     * @return string|null
     */
    public function resolve(int $siteId): ?string
    {
        if (!$this->request instanceof PhpEnvironmentRequest) {
            return null;
        }

        $cookieHeader = $this->request->getServer('HTTP_COOKIE');
        if (!is_string($cookieHeader) || $cookieHeader === '') {
            return null;
        }

        $namePattern = '/^_pk_id[._]' . preg_quote((string) $siteId, '/') . '[._]/';

        foreach (explode(';', $cookieHeader) as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }

            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            if (!preg_match($namePattern, trim($name))) {
                continue;
            }

            $visitorId = substr($value, 0, 16);
            if (preg_match('/^[0-9a-f]{16}$/i', $visitorId)) {
                return $visitorId;
            }
        }

        return null;
    }
}
