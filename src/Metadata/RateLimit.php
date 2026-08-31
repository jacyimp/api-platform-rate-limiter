<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata;

use DateInterval;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;

final readonly class RateLimit
{
    public function __construct(
        public int|DynamicLimit|null $limit = null,
        public string|DateInterval|Interval|null $interval = null,
        public RateLimitPolicy $policy = RateLimitPolicy::SLIDING_WINDOW,
        public ?string $identityResolver = null,
        public ?string $when = null,
        public string|DynamicBucket|null $bucket = null,
        public int|DynamicCost $cost = 1,
    ) {
        if (is_int($limit) && $limit < 1) {
            throw new InvalidRateLimitException(
                'Rate limit must be greater than zero.',
            );
        }

        if (($limit === null) !== ($interval === null)) {
            throw new InvalidRateLimitException(
                'Rate limit and interval must either both be set or both be omitted.',
            );
        }

        if ($limit === null && $bucket === null) {
            throw new InvalidRateLimitException(
                'An operation-specific rate limit requires a limit and interval.',
            );
        }

        if (is_string($bucket) && trim($bucket) === '') {
            throw new InvalidRateLimitException(
                'Rate limit bucket cannot be empty.',
            );
        }

        if (is_int($cost) && $cost < 1) {
            throw new InvalidRateLimitException(
                'Rate limit cost must be greater than zero.',
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
