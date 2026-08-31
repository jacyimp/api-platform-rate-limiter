<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata;

use DateInterval;
use InvalidArgumentException;

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
            throw new InvalidArgumentException(
                'Rate limit must be greater than zero.',
            );
        }

        if ($identityResolver !== null && trim($identityResolver) === '') {
            throw new InvalidArgumentException(
                'Identity resolver service ID cannot be empty.',
            );
        }

        if ($when !== null && trim($when) === '') {
            throw new InvalidArgumentException(
                'Rate limit condition service ID cannot be empty.',
            );
        }
    }
}
