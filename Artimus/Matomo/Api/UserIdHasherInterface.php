<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Api;

/**
 * Produces the hashed Matomo User ID for a logged-in customer.
 *
 * The hash never reveals the customer ID or the salt used to produce it.
 */
interface UserIdHasherInterface
{
    /**
     * Hash the given customer id into a Matomo-safe User ID.
     *
     * @param int $customerId
     * @return string
     */
    public function hash(int $customerId): string;
}
