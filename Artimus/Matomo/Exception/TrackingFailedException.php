<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * Thrown when sending an order to Matomo through the PHP SDK fails: an SDK exception, or a
 * non-2xx/empty response from the Matomo server. The caller decides whether to retry.
 */
class TrackingFailedException extends LocalizedException
{
}
