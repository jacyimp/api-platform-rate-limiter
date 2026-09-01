<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

/**
 * Resolves how many tokens the current request consumes.
 *
 * Reference an implementation with `new DynamicCost(SearchCostResolver::class)`.
 */
interface CostResolverInterface
{
    /** Return the positive number of tokens consumed by the current request. */
    public function resolve(): int;
}
