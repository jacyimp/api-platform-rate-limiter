<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

interface IdentityResolverInterface
{
    public function resolve(): string;
}
