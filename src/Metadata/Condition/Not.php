<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata\Condition;

/**
 * Matches when its child condition does not match.
 *
 * Example: `new Not(AuthenticatedCondition::class)`.
 */
final readonly class Not implements RateLimitCondition
{
    /** @param class-string<\JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface>|RateLimitCondition $condition */
    public function __construct(public string|RateLimitCondition $condition)
    {
    }
}
