<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

interface DynamicCostResolverInterface
{
    public function resolve(): int;
}
