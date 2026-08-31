<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

use DateTimeImmutable;

final readonly class RateLimitRejection
{
    public function __construct(
        public int $limit,
        public int $remaining,
        public DateTimeImmutable $retryAfter,
    ) {
    }
}
