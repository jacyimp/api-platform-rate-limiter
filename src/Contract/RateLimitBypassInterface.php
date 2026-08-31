<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

interface RateLimitBypassInterface
{
    public function shouldBypass(): bool;
}
