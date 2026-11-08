<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Model\PageContext;

use Artimus\Matomo\Api\PageContextProviderInterface;
use Magento\Catalog\Model\Category;
use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\Registry;

/**
 * Sends the Matomo ecommerce category view.
 */
class CategoryViewProvider implements PageContextProviderInterface
{
    private const ACTION = 'catalog_category_view';

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
        $category = $this->registry->registry('current_category');
        if (!$category instanceof Category) {
            return [];
        }

        return [['setEcommerceView', false, false, (string)$category->getName()]];
    }

    /**
     * @inheritdoc
     */
    public function replacesPageView(): bool
    {
        return false;
    }
}
