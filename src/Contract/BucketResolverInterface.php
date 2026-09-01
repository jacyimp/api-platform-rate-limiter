<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

/**
 * Resolves the bucket name used by a dynamic rate-limit declaration.
 *
 * Reference an implementation with `new DynamicBucket(TenantBucketResolver::class)`.
 */
interface BucketResolverInterface
{
    /** Return the non-empty bucket name for the current request. */
    public function resolve(): string;
}
