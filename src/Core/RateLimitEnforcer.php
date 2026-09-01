<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Core;

use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitBypassInterface;
use JacyImp\ApiPlatformRateLimiter\Event\RateLimitChecking;
use JacyImp\ApiPlatformRateLimiter\Event\RateLimitConsumed;
use JacyImp\ApiPlatformRateLimiter\Event\RateLimitRejected;
use JacyImp\ApiPlatformRateLimiter\Exception\IdentityResolutionException;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final readonly class RateLimitEnforcer
{
    public function __construct(
        private RateLimiterInterface $rateLimiter,
        private IdentityResolverInterface $identityResolver,
        private RateLimitBypassInterface $bypass,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param list<ResolvedRateLimit> $rateLimits
     */
    public function enforce(
        array $rateLimits,
    ): RateLimitEnforcementResult {
        $consumptions = [];

        foreach ($rateLimits as $rateLimit) {
            if ($this->bypass->shouldBypass()) {
                continue;
            }

            $identityResolver = $rateLimit->identityResolver
                ?? $this->identityResolver;
            $identity = $identityResolver->resolve();

            if ($identity === null || trim($identity) === '') {
                throw new IdentityResolutionException(
                    'Rate limit identity could not be resolved to a non-empty string.',
                );
            }
            $definition = $rateLimit->definition;

            $this->eventDispatcher->dispatch(new RateLimitChecking(
                bucket: $rateLimit->bucket,
                identity: $identity,
                limit: $definition->limit,
                intervalSeconds: $definition->intervalSeconds,
                policy: $definition->policy,
            ));

            $result = $this->rateLimiter->consume(
                rateLimit: $rateLimit,
                identity: $identity,
                tokens: $rateLimit->cost,
            );

            $consumptions[] = new RateLimitConsumption(
                rateLimit: $rateLimit,
                result: $result,
            );

            if (!$result->accepted) {
                $this->eventDispatcher->dispatch(new RateLimitRejected(
                    bucket: $rateLimit->bucket,
                    identity: $identity,
                    limit: $definition->limit,
                    intervalSeconds: $definition->intervalSeconds,
                    policy: $definition->policy,
                    remaining: $result->remaining,
                    retryAfter: $result->retryAfter,
                ));

                break;
            }

            $this->eventDispatcher->dispatch(new RateLimitConsumed(
                bucket: $rateLimit->bucket,
                identity: $identity,
                limit: $definition->limit,
                intervalSeconds: $definition->intervalSeconds,
                policy: $definition->policy,
                remaining: $result->remaining,
                retryAfter: $result->retryAfter,
            ));
        }

        return new RateLimitEnforcementResult($consumptions);
    }
}
