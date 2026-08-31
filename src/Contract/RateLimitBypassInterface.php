<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Contract;

use Jacyimp\ApiPlatformRateLimiter\Core\ResolvedRateLimit;

interface RateLimitBypassInterface
{
    public function shouldBypass(
        ResolvedRateLimit $rateLimit,
    ): bool;
}
