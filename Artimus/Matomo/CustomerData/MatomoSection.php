<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\CustomerData;

use Artimus\Matomo\Api\ConfigInterface;
use Artimus\Matomo\Api\UserIdHasherInterface;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Model\Session;

/**
 * Private `matomo` customer-data section carrying the hashed Matomo User ID.
 */
class MatomoSection implements SectionSourceInterface
{
    /**
     * @param Session $session
     * @param ConfigInterface $config
     * @param UserIdHasherInterface $hasher
     */
    public function __construct(
        private readonly Session $session,
        private readonly ConfigInterface $config,
        private readonly UserIdHasherInterface $hasher
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getSectionData(): array
    {
        if (!$this->session->isLoggedIn() || !$this->config->isUserIdTrackingEnabled()) {
            return [];
        }

        return ['userId' => $this->hasher->hash((int)$this->session->getCustomerId())];
    }
}
