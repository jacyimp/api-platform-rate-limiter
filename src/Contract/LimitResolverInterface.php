<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

interface LimitResolverInterface
{
    /** Return the positive request limit for the current request. */
    public function resolve(): int;
}
