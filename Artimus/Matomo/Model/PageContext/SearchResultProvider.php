<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Model\PageContext;

use Artimus\Matomo\Api\PageContextProviderInterface;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Framework\App\Request\Http as Request;
use Magento\Search\Model\QueryFactory;

/**
 * Sends Matomo site search instead of the plain page view.
 *
 * The result count is only correct because the tracker block renders after the product list.
 */
class SearchResultProvider implements PageContextProviderInterface
{
    private const ACTION = 'catalogsearch_result_index';

    /**
     * @param Request $request
     * @param QueryFactory $queryFactory
     * @param Resolver $layerResolver
     */
    public function __construct(
        private readonly Request $request,
        private readonly QueryFactory $queryFactory,
        private readonly Resolver $layerResolver
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getCommands(): array
    {
        if (!$this->isSearchPage()) {
            return [];
        }

        return [[
            'trackSiteSearch',
            (string)$this->queryFactory->get()->getQueryText(),
            false,
            (int)$this->layerResolver->get()->getProductCollection()->getSize()
        ]];
    }

    /**
     * @inheritdoc
     */
    public function replacesPageView(): bool
    {
        return $this->isSearchPage();
    }

    /**
     * Whether the current request is the search results page.
     *
     * @return bool
     */
    private function isSearchPage(): bool
    {
        return $this->request->getFullActionName() === self::ACTION;
    }
}
