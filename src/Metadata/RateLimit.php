<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata;

use DateInterval;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;

final readonly class RateLimit
{
    public function __construct(
        public int $limit,
        public string|DateInterval|Interval $interval,
        public RateLimitPolicy $policy = RateLimitPolicy::SLIDING_WINDOW,
        public ?string $identityResolver = null,
        public ?string $when = null,
    ) {
        if ($limit < 1) {
            throw new InvalidRateLimitException(
                'Rate limit must be greater than zero.',
            );
        }

        if ($identityResolver !== null && trim($identityResolver) === '') {
            throw new InvalidRateLimitException(
                'Identity resolver service ID cannot be empty.',
            );
        }

        if ($when !== null && trim($when) === '') {
            throw new InvalidRateLimitException(
                'Rate limit condition service ID cannot be empty.',
            );
        }
    }
}
