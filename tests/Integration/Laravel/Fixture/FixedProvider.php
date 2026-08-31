<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture;

use ApiPlatform\Metadata\Operation;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitProviderInterface;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

final class FixedProvider implements RateLimitProviderInterface
{
    /** @return iterable<RateLimit> */
    public function provide(Operation $operation): iterable
    {
        if ($operation->getName() !== 'provider') {
            return [];
        }

        return [new RateLimit(limit: 1, interval: '1 minute')];
    }
}
