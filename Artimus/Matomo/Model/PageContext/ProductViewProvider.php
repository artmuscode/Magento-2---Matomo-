<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Model\PageContext;

use Artimus\Matomo\Api\PageContextProviderInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\Registry;

/**
 * Sends the Matomo ecommerce product view. The price is the cached, customer-group-agnostic final price.
 */
class ProductViewProvider implements PageContextProviderInterface
{
    private const ACTION = 'catalog_product_view';
    private const MAX_CATEGORIES = 5;

    /**
     * @param Request $request
     * @param Registry $registry
     */
    public function __construct(
        private readonly Request $request,
        private readonly Registry $registry
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getCommands(): array
    {
        if ($this->request->getFullActionName() !== self::ACTION) {
            return [];
        }
        $product = $this->registry->registry('current_product');
        if (!$product instanceof Product) {
            return [];
        }

        return [[
            'setEcommerceView',
            (string)$product->getSku(),
            (string)$product->getName(),
            $this->getCategoryNames($product),
            (float)$product->getFinalPrice()
        ]];
    }

    /**
     * @inheritdoc
     */
    public function replacesPageView(): bool
    {
        return false;
    }

    /**
     * Up to five category names for the product.
     *
     * @param Product $product
     * @return string[]
     */
    private function getCategoryNames(Product $product): array
    {
        $names = [];
        foreach ($product->getCategoryCollection()->addAttributeToSelect('name')->getItems() as $category) {
            $names[] = (string)$category->getName();
        }

        return array_slice($names, 0, self::MAX_CATEGORIES);
    }
}
