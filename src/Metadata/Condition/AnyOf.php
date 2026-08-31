<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata\Condition;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;

final readonly class AnyOf implements RateLimitCondition
{
    /** @var list<RateLimitCondition> */
    public array $conditions;

    /** @param list<mixed> $conditions */
    public function __construct(array $conditions)
    {
        if ($conditions === []) {
            throw new InvalidRateLimitException(
                'AnyOf requires at least one condition.',
            );
        }

        foreach ($conditions as $condition) {
            if (!$condition instanceof RateLimitCondition) {
                throw new InvalidRateLimitException(
                    'AnyOf children must be rate limit condition expressions.',
                );
            }
        }

        $this->conditions = $conditions;
    }
}
