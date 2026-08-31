<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

interface BucketResolverInterface
{
    public function resolve(): string;
}
