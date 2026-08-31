<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

interface BucketResolverInterface
{
    /** Return the non-empty bucket name for the current request. */
    public function resolve(): string;
}
