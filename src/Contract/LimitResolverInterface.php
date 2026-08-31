<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

interface LimitResolverInterface
{
    public function resolve(): int;
}
