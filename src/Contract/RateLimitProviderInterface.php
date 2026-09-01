<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

use ApiPlatform\Metadata\Operation;

/**
 * Provides additional rate limits for the current API Platform operation.
 *
 * For example, return an hourly `RateLimit` only for an export operation.
 */
interface RateLimitProviderInterface
{
    /**
     * Return additive declarations for the operation, or an empty iterable.
     *
     * @return iterable<\JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit>
     */
    public function provide(Operation $operation): iterable;
}
