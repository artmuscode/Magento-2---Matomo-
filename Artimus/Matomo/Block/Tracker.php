<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Block;

use Artimus\Matomo\Api\ConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Renders the Matomo JS tracker bootstrap. Outputs nothing when tracking is inactive for the store.
 */
class Tracker extends Template
{
    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @param Context $context
     * @param ConfigInterface $config
     * @param array $data
     */
    public function __construct(Context $context, ConfigInterface $config, array $data = [])
    {
        $this->config = $config;
        parent::__construct($context, $data);
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml(): string
    {
        if (!$this->config->isTrackingActive()) {
            return '';
        }

        return parent::_toHtml();
    }
}
