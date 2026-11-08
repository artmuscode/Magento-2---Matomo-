<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Test\Unit\Model\PageContext;

use Artimus\Matomo\Model\PageContext\CategoryViewProvider;
use Artimus\Matomo\Model\PageContext\ProductViewProvider;
use Artimus\Matomo\Model\PageContext\SearchResultProvider;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\App\Request\Http as Request;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Framework\Registry;
use Magento\Search\Model\Query;
use Magento\Search\Model\QueryFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CatalogContextProvidersTest extends TestCase
{
    /**
     * @var Request&MockObject
     */
    private $request;

    /**
     * @var Registry&MockObject
     */
    private $registry;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Request::class);
        $this->registry = $this->createMock(Registry::class);
    }

    public function testItEmitsSetEcommerceViewWithSkuNameCategoriesAndPriceOnAProductPage(): void
    {
        $this->request->method('getFullActionName')->willReturn('catalog_product_view');
        $this->registry->method('registry')->with('current_product')
            ->willReturn($this->product(['Bags', 'Gear']));

        $provider = new ProductViewProvider($this->request, $this->registry);

        $this->assertSame(
            [['setEcommerceView', 'SKU-1', 'Duffle', ['Bags', 'Gear'], 34.0]],
            $provider->getCommands()
        );
        $this->assertFalse($provider->replacesPageView());
    }

    public function testItLimitsProductCategoriesToFiveNames(): void
    {
        $this->request->method('getFullActionName')->willReturn('catalog_product_view');
        $this->registry->method('registry')->willReturn($this->product(['a', 'b', 'c', 'd', 'e', 'f', 'g']));

        $commands = (new ProductViewProvider($this->request, $this->registry))->getCommands();

        $this->assertSame(['a', 'b', 'c', 'd', 'e'], $commands[0][3]);
    }

    public function testItEmitsSetEcommerceViewWithTheCategoryNameOnACategoryPage(): void
    {
        $this->request->method('getFullActionName')->willReturn('catalog_category_view');
        $category = $this->createMock(Category::class);
        $category->method('getName')->willReturn('Bags');
        $this->registry->method('registry')->with('current_category')->willReturn($category);

        $provider = new CategoryViewProvider($this->request, $this->registry);

        $this->assertSame([['setEcommerceView', false, false, 'Bags']], $provider->getCommands());
        $this->assertFalse($provider->replacesPageView());
    }

    public function testItEmitsTrackSiteSearchWithTheQueryAndResultCountOnASearchResultsPage(): void
    {
        $this->request->method('getFullActionName')->willReturn('catalogsearch_result_index');

        $this->assertSame([['trackSiteSearch', 'bag', false, 12]], $this->searchProvider()->getCommands());
    }

    public function testItReplacesTheDefaultPageViewOnASearchResultsPage(): void
    {
        $this->request->method('getFullActionName')->willReturn('catalogsearch_result_index');

        $this->assertTrue($this->searchProvider()->replacesPageView());
    }

    public function testItEmitsNoCommandsOnPagesOutsideItsActionName(): void
    {
        $this->request->method('getFullActionName')->willReturn('cms_index_index');
        $this->registry->expects($this->never())->method('registry');

        $this->assertSame([], (new ProductViewProvider($this->request, $this->registry))->getCommands());
        $this->assertSame([], (new CategoryViewProvider($this->request, $this->registry))->getCommands());
        $search = $this->searchProvider();
        $this->assertSame([], $search->getCommands());
        $this->assertFalse($search->replacesPageView());
    }

    /**
     * @param string[] $categoryNames
     * @return Product&MockObject
     */
    private function product(array $categoryNames): Product
    {
        $items = [];
        foreach ($categoryNames as $name) {
            $category = $this->createMock(Category::class);
            $category->method('getName')->willReturn($name);
            $items[] = $category;
        }
        $collection = $this->createMock(CategoryCollection::class);
        $collection->method('getItems')->willReturn($items);
        $collection->method('addAttributeToSelect')->willReturnSelf();

        $product = $this->createMock(Product::class);
        $product->method('getSku')->willReturn('SKU-1');
        $product->method('getName')->willReturn('Duffle');
        $product->method('getFinalPrice')->willReturn(34.0);
        $product->method('getCategoryCollection')->willReturn($collection);

        return $product;
    }

    private function searchProvider(): SearchResultProvider
    {
        $query = $this->createMock(Query::class);
        $query->method('getQueryText')->willReturn('bag');
        $queryFactory = $this->createMock(QueryFactory::class);
        $queryFactory->method('get')->willReturn($query);

        $collection = $this->createMock(ProductCollection::class);
        $collection->method('getSize')->willReturn(12);
        $layer = $this->createMock(Layer::class);
        $layer->method('getProductCollection')->willReturn($collection);
        $resolver = $this->createMock(Resolver::class);
        $resolver->method('get')->willReturn($layer);

        return new SearchResultProvider($this->request, $queryFactory, $resolver);
    }
}
