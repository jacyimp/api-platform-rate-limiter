<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata\Condition;

/**
 * Matches when its child condition does not match.
 *
 * Example: `new Not(new Condition(AuthenticatedCondition::class))`.
 */
final readonly class Not implements RateLimitCondition
{
    public function __construct(public RateLimitCondition $condition)
    {
    }
}
