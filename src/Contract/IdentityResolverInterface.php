<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Contract;

interface IdentityResolverInterface
{
    public function resolve(): string;
}
