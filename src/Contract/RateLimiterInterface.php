<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Contract;

use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitResult;
use Jacyimp\ApiPlatformRateLimiter\Core\ResolvedRateLimit;

interface RateLimiterInterface
{
    public function consume(
        ResolvedRateLimit $rateLimit,
        string $identity,
        int $tokens = 1,
    ): RateLimitResult;
}
