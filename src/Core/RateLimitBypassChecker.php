<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Core;

use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitBypassInterface;

final readonly class RateLimitBypassChecker implements RateLimitBypassInterface
{
    /**
     * @param iterable<RateLimitBypassInterface> $bypasses
     */
    public function __construct(
        private iterable $bypasses,
    ) {
    }

    public function shouldBypass(
        ResolvedRateLimit $rateLimit,
    ): bool {
        foreach ($this->bypasses as $bypass) {
            if ($bypass->shouldBypass($rateLimit)) {
                return true;
            }
        }

        return false;
    }
}
