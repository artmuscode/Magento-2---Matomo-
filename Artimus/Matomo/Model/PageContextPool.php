<?php
/**
 * Copyright © Artimus Code. All rights reserved.
 */
declare(strict_types=1);

namespace Artimus\Matomo\Model;

use Artimus\Matomo\Api\PageContextProviderInterface;

/**
 * Aggregates the registered `Api\PageContextProviderInterface` providers for the current page.
 *
 * Providers are injected via the `providers` di.xml array argument in `etc/frontend/di.xml`.
 */
class PageContextPool
{
    /**
     * @var PageContextProviderInterface[]
     */
    private $providers;

    /**
     * @param PageContextProviderInterface[] $providers
     */
    public function __construct(array $providers = [])
    {
        $this->providers = $providers;
    }

    /**
     * The commands from every registered provider, in registration order.
     *
     * @return array
     */
    public function getCommands(): array
    {
        $commands = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->getCommands() as $command) {
                $commands[] = $command;
            }
        }

        return $commands;
    }

    /**
     * Whether any registered provider replaces the default page view.
     *
     * @return bool
     */
    public function replacesPageView(): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->replacesPageView()) {
                return true;
            }
        }

        return false;
    }
}
