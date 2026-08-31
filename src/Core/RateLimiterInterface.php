<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Core;

/**
 * @internal
 */
interface RateLimiterInterface
{
    public function consume(
        ResolvedRateLimit $rateLimit,
        string $identity,
        int $tokens = 1,
    ): RateLimitResult;
}
