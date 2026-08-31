<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Event;

use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;

final readonly class RateLimitChecking
{
    public function __construct(
        public string $bucket,
        public string $identity,
        public int $limit,
        public int $intervalSeconds,
        public RateLimitPolicy $policy,
    ) {
    }
}
