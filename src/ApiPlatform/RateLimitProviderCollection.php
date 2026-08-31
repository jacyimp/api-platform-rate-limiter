<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\ApiPlatform;

use ApiPlatform\Metadata\Operation;

/**
 * @internal
 */
final readonly class RateLimitProviderCollection
{
    /**
     * @param iterable<
     *     \JacyImp\ApiPlatformRateLimiter\Contract\RateLimitProviderInterface
     * > $providers
     */
    public function __construct(
        private iterable $providers,
    ) {
    }

    /**
     * @return list<
     *     \JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit
     *     |\JacyImp\ApiPlatformRateLimiter\Metadata\SharedRateLimit
     * >
     */
    public function provide(Operation $operation): array
    {
        $rateLimits = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->provide($operation) as $rateLimit) {
                $rateLimits[] = $rateLimit;
            }
        }

        return $rateLimits;
    }
}
