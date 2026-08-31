<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Core;

use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitBypassInterface;

/**
 * @internal
 */
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
        if ($rateLimits === [] || $this->bypass->shouldBypass()) {
            return new RateLimitEnforcementResult([]);
        }

        $identity = $this->identityResolver->resolve();

        $consumptions = [];

        foreach ($rateLimits as $rateLimit) {
            $result = $this->rateLimiter->consume(
                rateLimit: $rateLimit,
                identity: $identity,
            );

            $consumptions[] = new RateLimitConsumption(
                rateLimit: $rateLimit,
                result: $result,
            );

            if (!$result->accepted) {
                break;
            }
        }

        return new RateLimitEnforcementResult($consumptions);
    }
}
