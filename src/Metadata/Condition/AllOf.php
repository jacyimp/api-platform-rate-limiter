<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata\Condition;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;

/**
 * Matches when every child condition matches.
 *
 * Example: `new AllOf([new Condition(IsUser::class), new Condition(IsPaid::class)])`.
 */
final readonly class AllOf implements RateLimitCondition
{
    /** @var list<RateLimitCondition> */
    public array $conditions;

    /** @param list<mixed> $conditions */
    public function __construct(array $conditions)
    {
        if ($conditions === []) {
            throw new InvalidRateLimitException(
                'AllOf requires at least one condition.',
            );
        }

        $validated = [];
        foreach ($conditions as $condition) {
            if (!$condition instanceof RateLimitCondition) {
                throw new InvalidRateLimitException(
                    'AllOf children must be rate limit condition expressions.',
                );
            }

            $validated[] = $condition;
        }

        $this->conditions = $validated;
    }
}
