<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

/**
 * Decides whether all rate limits should be skipped for the current request.
 *
 * For example, an implementation can exempt authenticated internal traffic.
 */
interface RateLimitBypassInterface
{
    /** Return true to skip every resolved limit for the current request. */
    public function shouldBypass(): bool;
}
