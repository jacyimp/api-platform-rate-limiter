<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata;

/**
 * Selects how requests are counted within a rate-limit interval.
 *
 * Example: `new RateLimit(limit: 100, interval: '1 minute', policy: RateLimitPolicy::FIXED_WINDOW)`.
 */
enum RateLimitPolicy: string
{
    /** Reset the counter at each interval boundary. */
    case FIXED_WINDOW = 'fixed_window';

    /** Smooth usage across adjacent interval boundaries. */
    case SLIDING_WINDOW = 'sliding_window';
}
