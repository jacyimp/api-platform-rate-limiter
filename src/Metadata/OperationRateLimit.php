<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Metadata;

use DateInterval;
use InvalidArgumentException;

final readonly class OperationRateLimit
{
    public function __construct(
        public int $limit,
        public string|DateInterval|Interval $interval,
        public RateLimitPolicy $policy = RateLimitPolicy::SLIDING_WINDOW,
    ) {
        if ($limit < 1) {
            throw new InvalidArgumentException(
                'Rate limit must be greater than zero.',
            );
        }
    }
}
