<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Model\PageContext;

use Artimus\Matomo\Api\PageContextProviderInterface;
use Magento\Framework\App\Request\Http;

/**
 * Suppresses the default page view on the checkout page: its steps send virtual page views instead
 * (`Artimus_Matomo/js/checkout-steps`). The output is identical for every visitor, so it is FPC-safe.
 */
class CheckoutPageProvider implements PageContextProviderInterface
{
    private const CHECKOUT_ACTION = 'checkout_index_index';

    /**
     * @var Http
     */
    private $request;

    /**
     * @param Http $request
     */
    public function __construct(Http $request)
    {
        $this->request = $request;
    }

    /**
     * @inheritdoc
     */
    public function getCommands(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function replacesPageView(): bool
    {
        return $this->request->getFullActionName() === self::CHECKOUT_ACTION;
    }
}
