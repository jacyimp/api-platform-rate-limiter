<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

interface CostResolverInterface
{
    /** Return the positive number of tokens consumed by the current request. */
    public function resolve(): int;
}
