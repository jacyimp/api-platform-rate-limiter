<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Core;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RateLimitResult
{
    public function __construct(
        public bool $accepted,
        public int $remaining,
        public DateTimeImmutable $retryAfter,
    ) {
        if ($remaining < 0) {
            throw new InvalidArgumentException(
                'Remaining tokens cannot be negative.',
            );
        }
    }
}
