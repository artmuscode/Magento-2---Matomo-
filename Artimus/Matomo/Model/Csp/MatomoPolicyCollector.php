<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Model\Csp;

use Artimus\Matomo\Api\ConfigInterface;
use Magento\Csp\Api\PolicyCollectorInterface;
use Magento\Csp\Model\Policy\FetchPolicy;

/**
 * Allow-lists the configured Matomo tracker host for script-src, connect-src and img-src.
 */
class MatomoPolicyCollector implements PolicyCollectorInterface
{
    private const POLICY_IDS = ['script-src', 'connect-src', 'img-src'];

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @param ConfigInterface $config
     */
    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function collect(array $defaultPolicies = []): array
    {
        if (!$this->config->isTrackingActive()) {
            return $defaultPolicies;
        }

        $host = $this->getAllowedHost($this->config->getTrackerUrl());
        if ($host === null) {
            return $defaultPolicies;
        }

        $policies = $defaultPolicies;
        foreach (self::POLICY_IDS as $id) {
            $policies[] = new FetchPolicy($id, false, [$host]);
        }

        return $policies;
    }

    /**
     * Extract the host(+port) allowed by CSP from the tracker URL, or null when it can't be parsed.
     *
     * @param string $trackerUrl
     * @return string|null
     */
    private function getAllowedHost(string $trackerUrl): ?string
    {
        $parts = parse_url($trackerUrl);
        if (!isset($parts['host'])) {
            return null;
        }

        $host = $parts['host'];
        if (!empty($parts['port']) && !$this->isDefaultPort($parts['scheme'] ?? '', (int) $parts['port'])) {
            $host .= ':' . $parts['port'];
        }

        return $host;
    }

    /**
     * Whether the given port is the implicit default for the given scheme.
     *
     * @param string $scheme
     * @param int $port
     * @return bool
     */
    private function isDefaultPort(string $scheme, int $port): bool
    {
        return ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);
    }
}
