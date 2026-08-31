<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Core;

use Jacyimp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use Jacyimp\ApiPlatformRateLimiter\Contract\RateLimitBypassInterface;
use Jacyimp\ApiPlatformRateLimiter\Contract\RateLimiterInterface;

final readonly class RateLimitEnforcer
{
    public function __construct(
        private RateLimiterInterface $rateLimiter,
        private IdentityResolverInterface $identityResolver,
        private RateLimitBypassInterface $bypass,
    ) {
    }

    /**
     * @param list<ResolvedRateLimit> $rateLimits
     */
    public function enforce(
        array $rateLimits,
    ): RateLimitEnforcementResult {
        if ($rateLimits === []) {
            return new RateLimitEnforcementResult([]);
        }

        $identity = $this->identityResolver->resolve();

        $results = [];

        foreach ($rateLimits as $rateLimit) {
            if ($this->bypass->shouldBypass($rateLimit)) {
                continue;
            }

            $result = $this->rateLimiter->consume(
                rateLimit: $rateLimit,
                identity: $identity,
            );

            $results[] = $result;

            if (!$result->accepted) {
                break;
            }
        }

        return new RateLimitEnforcementResult($results);
    }
}
