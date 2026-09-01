<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

use DateTimeImmutable;

/**
 * Describes the exceeded limit passed to a custom rejection handler.
 *
 * For example, use `$rejection->retryAfter` to populate the `Retry-After` header.
 */
final readonly class RateLimitRejection
{
    public function __construct(
        public int $limit,
        public int $remaining,
        public DateTimeImmutable $retryAfter,
    ) {
    }
}
