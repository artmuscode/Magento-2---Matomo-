<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\ViewModel;

use Artimus\Matomo\Api\ConfigInterface;
use Artimus\Matomo\Api\ConsentCheckerInterface;
use Artimus\Matomo\Model\PageContextPool;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Builds the cacheable JS config for the Matomo tracker bootstrap (`view/frontend/templates/tracker.phtml`).
 *
 * Only store/catalog-level data is used here: this output is rendered into full-page-cached HTML, so
 * only `ConsentCheckerInterface::isConsentRequired()` (store config) may be used, never `hasConsent()`
 * (reads the visitor's cookie).
 */
class TrackerConfig implements ArgumentInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var ConsentCheckerInterface
     */
    private $consentChecker;

    /**
     * @var PageContextPool
     */
    private $pageContextPool;

    /**
     * @var JsonHexTag
     */
    private $jsonHexTag;

    /**
     * @var array
     */
    private $jsComponents;

    /**
     * @param ConfigInterface $config
     * @param ConsentCheckerInterface $consentChecker
     * @param PageContextPool $pageContextPool
     * @param JsonHexTag $jsonHexTag
     * @param array $jsComponents Component path => config array, registered via di.xml.
     */
    public function __construct(
        ConfigInterface $config,
        ConsentCheckerInterface $consentChecker,
        PageContextPool $pageContextPool,
        JsonHexTag $jsonHexTag,
        array $jsComponents = []
    ) {
        $this->config = $config;
        $this->consentChecker = $consentChecker;
        $this->pageContextPool = $pageContextPool;
        $this->jsonHexTag = $jsonHexTag;
        $this->jsComponents = $jsComponents;
    }

    /**
     * The config passed to `Artimus_Matomo/js/tracker` via `x-magento-init`.
     *
     * @return array
     */
    public function getTrackerConfig(): array
    {
        return [
            'trackerUrl' => $this->config->getTrackerUrl(),
            'siteId' => $this->config->getSiteId(),
            'requireConsent' => $this->consentChecker->isConsentRequired(),
            'providerCommands' => $this->pageContextPool->getCommands(),
            'replacesPageView' => $this->pageContextPool->replacesPageView(),
        ];
    }

    /**
     * The full `x-magento-init` config: the tracker component first, then every registered companion.
     *
     * @return array
     */
    public function getMageInitConfig(): array
    {
        return [
            '*' => ['Artimus_Matomo/js/tracker' => $this->getTrackerConfig()] + $this->jsComponents,
        ];
    }

    /**
     * The `x-magento-init` config as JSON, safe to print inside a script tag.
     *
     * `<`, `>`, `&`, `'` and `"` are hex-escaped so catalog data (product/category names) can't
     * close the surrounding script tag.
     *
     * @return string
     */
    public function getMageInitJson(): string
    {
        return (string) $this->jsonHexTag->serialize($this->getMageInitConfig());
    }
}
