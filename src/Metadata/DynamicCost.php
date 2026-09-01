<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;

/**
 * Resolves the current request's token cost at request time.
 *
 * Example: `new DynamicCost(SearchCostResolver::class)`.
 */
final readonly class DynamicCost
{
    /** @param non-empty-string|class-string<\JacyImp\ApiPlatformRateLimiter\Contract\CostResolverInterface> $resolver */
    public function __construct(public string $resolver)
    {
        if (trim($resolver) === '') {
            throw new InvalidRateLimitException(
                'Cost resolver service ID cannot be empty.',
            );
        }
    }
}
