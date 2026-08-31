<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use JacyImp\ApiPlatformRateLimiter\Core\IntervalNormalizer;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitDefinition;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitStrategyRegistry;
use JacyImp\ApiPlatformRateLimiter\Core\ResolvedRateLimit;
use JacyImp\ApiPlatformRateLimiter\Core\SharedRateLimitRegistry;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicBucket;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicCost;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

/**
 * @internal
 */
final readonly class RateLimitResolver
{
    public function __construct(
        private RateLimitMetadataExtractor $metadataExtractor,
        private RateLimitProviderCollection $providerCollection,
        private IntervalNormalizer $intervalNormalizer,
        private SharedRateLimitRegistry $sharedRateLimitRegistry,
        private RateLimitStrategyRegistry $strategyRegistry,
        private ?RateLimitDefinition $globalRateLimit = null,
    ) {
    }

    /**
     * @return list<ResolvedRateLimit>
     */
    public function resolve(
        Operation $operation,
        string $operationKey,
    ): array {
        $rateLimits = [
            ...$this->metadataExtractor->extract($operation),
            ...$this->providerCollection->provide($operation),
        ];

        $resolved = [];

        foreach ($rateLimits as $rateLimit) {
            $resolved[] = $this->resolveRateLimit(
                rateLimit: $rateLimit,
                operationKey: $operationKey,
            );
        }

        if ($this->globalRateLimit !== null) {
            $resolved[] = new ResolvedRateLimit(
                bucket: 'global',
                definition: $this->globalRateLimit,
                identityResolver: $this->globalRateLimit->identityResolver === null
                    ? null
                    : $this->strategyRegistry->identityResolver(
                        $this->globalRateLimit->identityResolver,
                    ),
                condition: $this->globalRateLimit->when === null
                    ? null
                    : $this->strategyRegistry->condition(
                        $this->globalRateLimit->when,
                    ),
            );
        }

        return $resolved;
    }

    private function resolveRateLimit(RateLimit $rateLimit, string $operationKey,): ResolvedRateLimit
    {
        $bucket = $this->resolveBucket($rateLimit, $operationKey);
        $definition = $this->resolveDefinition($rateLimit, $bucket);
        $identityResolver = $rateLimit->identityResolver
            ?? $definition->identityResolver;
        $when = $rateLimit->when ?? $definition->when;

        return new ResolvedRateLimit(
            bucket: $rateLimit->bucket === null
                ? sprintf('operation:%s', $bucket)
                : sprintf('shared:%s', $bucket),
            definition: $definition,
            identityResolver: $identityResolver === null
                ? null
                : $this->strategyRegistry->identityResolver(
                    $identityResolver,
                ),
            condition: $when === null
                ? null
                : $this->strategyRegistry->condition($when),
            cost: $this->resolveCost($rateLimit),
        );
    }

    private function resolveCost(RateLimit $rateLimit): int
    {
        if (!$rateLimit->cost instanceof DynamicCost) {
            return $rateLimit->cost;
        }

        return $this->strategyRegistry
            ->costResolver($rateLimit->cost->resolver)
            ->resolve();
    }

    private function resolveBucket(RateLimit $rateLimit, string $operationKey,): string
    {
        if ($rateLimit->bucket instanceof DynamicBucket) {
            $bucket = $this->strategyRegistry
                ->bucketResolver($rateLimit->bucket->resolver)
                ->resolve();
        } else {
            $bucket = $rateLimit->bucket ?? $operationKey;
        }

        if (trim($bucket) === '') {
            throw new InvalidRateLimitException($rateLimit->bucket === null
                    ? 'Operation key cannot be empty.'
                    : 'Resolved rate limit bucket cannot be empty.',);
        }

        return $bucket;
    }

    private function resolveDefinition(RateLimit $rateLimit, string $bucket,): RateLimitDefinition
    {
        if ($rateLimit->limit === null) {
            return $this->sharedRateLimitRegistry->get($bucket);
        }

        $limit = $rateLimit->limit instanceof DynamicLimit
            ? $this->strategyRegistry
                ->limitResolver($rateLimit->limit->resolver)
                ->resolve()
            : $rateLimit->limit;
        $interval = $rateLimit->interval;
        if ($interval === null) {
            throw new InvalidRateLimitException(
                'Rate limit interval cannot be omitted when a limit is set.',
            );
        }

        return new RateLimitDefinition(
            limit: $limit,
            intervalSeconds: $this->intervalNormalizer->normalize($interval),
            policy: $rateLimit->policy,
        );
    }
}
