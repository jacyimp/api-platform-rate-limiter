<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

use ApiPlatform\Metadata\Operation;

interface RateLimitProviderInterface
{
    /**
     * @return iterable<
     *     \JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit
     *     |\JacyImp\ApiPlatformRateLimiter\Metadata\SharedRateLimit
     * >
     */
    public function provide(Operation $operation): iterable;
}
