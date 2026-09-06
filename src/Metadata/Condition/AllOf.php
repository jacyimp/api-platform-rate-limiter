<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata\Condition;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;

/**
 * Matches when every child condition matches.
 *
 * Example: `new AllOf([IsUser::class, IsPaid::class])`.
 */
final readonly class AllOf implements RateLimitCondition
{
    /** @var non-empty-list<class-string<\JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface>|RateLimitCondition> */
    public array $conditions;

    /** @param list<class-string<\JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface>|RateLimitCondition> $conditions */
    public function __construct(array $conditions)
    {
        if ($conditions === []) {
            throw new InvalidRateLimitException(
                'AllOf requires at least one condition.',
            );
        }

        $this->conditions = $conditions;
    }
}
