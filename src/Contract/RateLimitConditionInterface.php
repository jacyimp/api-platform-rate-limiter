<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

/**
 * Evaluates whether a conditional rate limit or bypass applies.
 *
 * Reference an implementation with `new Condition(AuthenticatedCondition::class)`.
 */
interface RateLimitConditionInterface
{
    /** Return true when the declaration should apply to the current request. */
    public function matches(): bool;
}
