<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

use JacyImp\ApiPlatformRateLimiter\Core\RateLimitResult;
use JacyImp\ApiPlatformRateLimiter\Core\ResolvedRateLimit;

interface RateLimiterInterface
{
    public function consume(
        ResolvedRateLimit $rateLimit,
        string $identity,
        int $tokens = 1,
    ): RateLimitResult;
}
