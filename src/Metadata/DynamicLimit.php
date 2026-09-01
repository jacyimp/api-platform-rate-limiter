<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;

/**
 * Resolves a rate-limit allowance from application state at request time.
 *
 * Example: `new DynamicLimit(PlanLimitResolver::class)`.
 */
final readonly class DynamicLimit
{
    /** @param non-empty-string|class-string<\JacyImp\ApiPlatformRateLimiter\Contract\LimitResolverInterface> $resolver */
    public function __construct(public string $resolver)
    {
        if (trim($resolver) === '') {
            throw new InvalidRateLimitException(
                'Limit resolver service ID cannot be empty.',
            );
        }
    }
}
