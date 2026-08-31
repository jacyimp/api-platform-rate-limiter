<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

use JacyImp\ApiPlatformRateLimiter\Core\ResolvedRateLimit;

interface RateLimitBypassInterface
{
    public function shouldBypass(
        ResolvedRateLimit $rateLimit,
    ): bool;
}
