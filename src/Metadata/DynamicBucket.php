<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;

/**
 * Resolves a rate-limit bucket name from application state at request time.
 *
 * Example: `new DynamicBucket(TenantBucketResolver::class)`.
 */
final readonly class DynamicBucket
{
    /** @param non-empty-string|class-string<\JacyImp\ApiPlatformRateLimiter\Contract\BucketResolverInterface> $resolver */
    public function __construct(public string $resolver)
    {
        if (trim($resolver) === '') {
            throw new InvalidRateLimitException(
                'Bucket resolver service ID cannot be empty.',
            );
        }
    }
}
