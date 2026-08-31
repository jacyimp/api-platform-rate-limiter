<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Core;

final readonly class RateLimitConsumption
{
    public function __construct(
        public ResolvedRateLimit $rateLimit,
        public RateLimitResult $result,
    ) {
    }
}
