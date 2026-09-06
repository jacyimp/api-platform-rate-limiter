<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata\Condition;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;

/**
 * Matches when at least one child condition matches.
 *
 * Example: `new AnyOf([IsUser::class, IsGuest::class])`.
 */
final readonly class AnyOf implements RateLimitCondition
{
    /** @var non-empty-list<class-string<\JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface>|RateLimitCondition> */
    public array $conditions;

    /** @param list<class-string<\JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface>|RateLimitCondition> $conditions */
    public function __construct(array $conditions)
    {
        if ($conditions === []) {
            throw new InvalidRateLimitException(
                'AnyOf requires at least one condition.',
            );
        }

        $this->conditions = $conditions;
    }
}
