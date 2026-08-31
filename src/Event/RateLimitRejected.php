<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Event;

use DateTimeImmutable;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;

final readonly class RateLimitRejected
{
    public function __construct(
        public string $bucket,
        public string $identity,
        public int $limit,
        public int $intervalSeconds,
        public RateLimitPolicy $policy,
        public int $remaining,
        public DateTimeImmutable $retryAfter,
    ) {
    }
}
