<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

/**
 * Resolves the request allowance for a dynamic rate limit.
 *
 * Reference an implementation with `new DynamicLimit(PlanLimitResolver::class)`.
 */
interface LimitResolverInterface
{
    /** Return the positive request limit for the current request. */
    public function resolve(): int;
}
