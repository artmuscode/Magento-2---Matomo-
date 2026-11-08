<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit\ViewModel;

use Artimus\Matomo\Api\ConfigInterface;
use Artimus\Matomo\Api\ConsentCheckerInterface;
use Artimus\Matomo\Api\PageContextProviderInterface;
use Artimus\Matomo\Model\PageContextPool;
use Artimus\Matomo\ViewModel\TrackerConfig;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TrackerConfigTest extends TestCase
{
    /**
     * @var ConfigInterface&MockObject
     */
    private $config;

    /**
     * @var ConsentCheckerInterface&MockObject
     */
    private $consentChecker;

    /**
     * @param array $providers
     * @param array $jsComponents
     * @return TrackerConfig
     */
    private function createViewModel(array $providers = [], array $jsComponents = []): TrackerConfig
    {
        return new TrackerConfig(
            $this->config,
            $this->consentChecker,
            new PageContextPool($providers),
            new JsonHexTag(),
            $jsComponents
        );
    }

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->consentChecker = $this->createMock(ConsentCheckerInterface::class);
    }

    public function testItExposesTrackerUrlSiteIdAndRequireConsentInTheJsConfigWhenActive(): void
    {
        $this->config->method('getTrackerUrl')->willReturn('https://matomo.test/');
        $this->config->method('getSiteId')->willReturn(3);
        $this->consentChecker->method('isConsentRequired')->willReturn(true);

        $viewModel = $this->createViewModel();

        $trackerConfig = $viewModel->getTrackerConfig();

        $this->assertSame('https://matomo.test/', $trackerConfig['trackerUrl']);
        $this->assertSame(3, $trackerConfig['siteId']);
        $this->assertTrue($trackerConfig['requireConsent']);
    }

    public function testItCollectsCommandsFromEveryRegisteredPageContextProviderInOrder(): void
    {
        $firstProvider = $this->createMock(PageContextProviderInterface::class);
        $firstProvider->method('getCommands')->willReturn([['setEcommerceView', 'SKU1']]);
        $firstProvider->method('replacesPageView')->willReturn(false);

        $secondProvider = $this->createMock(PageContextProviderInterface::class);
        $secondProvider->method('getCommands')->willReturn([['trackSiteSearch', 'keyword']]);
        $secondProvider->method('replacesPageView')->willReturn(false);

        $viewModel = $this->createViewModel([$firstProvider, $secondProvider]);

        $this->assertSame(
            [['setEcommerceView', 'SKU1'], ['trackSiteSearch', 'keyword']],
            $viewModel->getTrackerConfig()['providerCommands']
        );
    }

    public function testItOmitsTheDefaultPageViewWhenAProviderReplacesIt(): void
    {
        $provider = $this->createMock(PageContextProviderInterface::class);
        $provider->method('getCommands')->willReturn([]);
        $provider->method('replacesPageView')->willReturn(true);

        $viewModel = $this->createViewModel([$provider]);

        $this->assertTrue($viewModel->getTrackerConfig()['replacesPageView']);
    }

    public function testItNeverAsksTheConsentCheckerForTheVisitorsConsentWhenBuildingCacheableConfig(): void
    {
        $this->consentChecker->expects($this->never())->method('hasConsent');

        $viewModel = $this->createViewModel([], ['Artimus_Matomo/js/cart-tracker' => []]);

        $viewModel->getTrackerConfig();
        $viewModel->getMageInitConfig();
    }

    public function testItRendersRegisteredJsComponentsAlongsideTheTrackerComponentInTheXMagentoInitConfig(): void
    {
        $this->config->method('getTrackerUrl')->willReturn('https://matomo.test/');
        $this->config->method('getSiteId')->willReturn(1);

        $viewModel = $this->createViewModel(
            [],
            [
                'Artimus_Matomo/js/cart-tracker' => [],
                'Artimus_Matomo/js/user-id' => ['sectionName' => 'matomo'],
            ]
        );

        $mageInit = $viewModel->getMageInitConfig();

        $this->assertSame(['*'], array_keys($mageInit));
        $this->assertSame(
            ['Artimus_Matomo/js/tracker', 'Artimus_Matomo/js/cart-tracker', 'Artimus_Matomo/js/user-id'],
            array_keys($mageInit['*'])
        );
        $this->assertSame($viewModel->getTrackerConfig(), $mageInit['*']['Artimus_Matomo/js/tracker']);
        $this->assertSame([], $mageInit['*']['Artimus_Matomo/js/cart-tracker']);
        $this->assertSame(['sectionName' => 'matomo'], $mageInit['*']['Artimus_Matomo/js/user-id']);
    }

    public function testItEscapesHtmlSignificantCharactersInTheXMagentoInitJson(): void
    {
        $provider = $this->createMock(PageContextProviderInterface::class);
        $provider->method('getCommands')->willReturn([['setEcommerceView', 'SKU1', '</script><b>"x"']]);
        $provider->method('replacesPageView')->willReturn(false);

        $json = $this->createViewModel([$provider])->getMageInitJson();

        $this->assertStringNotContainsString('</script>', $json);
        $this->assertStringNotContainsString('<b>', $json);
        $this->assertSame(
            '</script><b>"x"',
            json_decode($json, true)['*']['Artimus_Matomo/js/tracker']['providerCommands'][0][2]
        );
    }
}
