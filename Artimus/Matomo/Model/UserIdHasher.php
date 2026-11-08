<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Model;

use Artimus\Matomo\Api\UserIdHasherInterface;
use Magento\Framework\App\DeploymentConfig;

/**
 * Hashes a customer id with the deployment's crypt key, so Matomo never sees the raw id.
 */
class UserIdHasher implements UserIdHasherInterface
{
    private const CONFIG_PATH_CRYPT_KEY = 'crypt/key';

    /**
     * @var DeploymentConfig
     */
    private $deploymentConfig;

    /**
     * @param DeploymentConfig $deploymentConfig
     */
    public function __construct(DeploymentConfig $deploymentConfig)
    {
        $this->deploymentConfig = $deploymentConfig;
    }

    /**
     * @inheritDoc
     */
    public function hash(int $customerId): string
    {
        return hash_hmac('sha256', (string) $customerId, $this->getSalt());
    }

    /**
     * The first non-empty line of the configured crypt key(s).
     *
     * @return string
     */
    private function getSalt(): string
    {
        $keys = (string) $this->deploymentConfig->get(self::CONFIG_PATH_CRYPT_KEY);

        return explode("\n", $keys)[0];
    }
}
