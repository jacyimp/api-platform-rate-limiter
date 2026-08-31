<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Symfony;

use InvalidArgumentException;
use Jacyimp\ApiPlatformRateLimiter\Contract\RateLimiterInterface;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitResult;
use Jacyimp\ApiPlatformRateLimiter\Core\ResolvedRateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

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
            throw new InvalidArgumentException(
                'Rate limit identity cannot be empty.',
            );
        }

        if ($tokens < 1) {
            throw new InvalidArgumentException(
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
                $this->storageKey(
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

    private function storageKey(
        ResolvedRateLimit $rateLimit,
        string $identity,
    ): string {
        $definition = $rateLimit->definition;

        return hash(
            'sha256',
            implode("\0", [
                $rateLimit->bucket,
                $identity,
                $definition->policy->value,
                (string) $definition->limit,
                (string) $definition->intervalSeconds,
            ]),
        );
    }
}
