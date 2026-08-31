<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Core;

use DateTimeImmutable;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;

/**
 * @internal
 */
final readonly class RateLimitResult
{
    public function __construct(
        public bool $accepted,
        public int $remaining,
        public DateTimeImmutable $retryAfter,
    ) {
        if ($remaining < 0) {
            throw new InvalidRateLimitException(
                'Remaining tokens cannot be negative.',
            );
        }
    }
}
