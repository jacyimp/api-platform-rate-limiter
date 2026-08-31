<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Symfony;

use JacyImp\ApiPlatformRateLimiter\Core\LimiterStorageKey;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimiterInterface;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitResult;
use JacyImp\ApiPlatformRateLimiter\Core\ResolvedRateLimit;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * @internal
 */
final readonly class SymfonyRateLimiter implements RateLimiterInterface
{
    public function __construct(
        private StorageInterface $storage,
    ) {
    }

    public function consume(
        ResolvedRateLimit $rateLimit,
        string $identity,
        int $tokens = 1,
    ): RateLimitResult {
        if (trim($identity) === '') {
            throw new InvalidRateLimitException(
                'Rate limit identity cannot be empty.',
            );
        }

        if ($tokens < 1) {
            throw new InvalidRateLimitException(
                'Consumed tokens must be greater than zero.',
            );
        }

        $definition = $rateLimit->definition;

        $factory = new RateLimiterFactory(
            config: [
                'id' => 'api_platform_rate_limiter',
                'policy' => $definition->policy->value,
                'limit' => $definition->limit,
                'interval' => sprintf(
                    '%d seconds',
                    $definition->intervalSeconds,
                ),
            ],
            storage: $this->storage,
        );

        $result = $factory
            ->create(
                LimiterStorageKey::for(
                    rateLimit: $rateLimit,
                    identity: $identity,
                ),
            )
            ->consume($tokens);

        return new RateLimitResult(
            accepted: $result->isAccepted(),
            remaining: $result->getRemainingTokens(),
            retryAfter: $result->getRetryAfter(),
        );
    }
}
