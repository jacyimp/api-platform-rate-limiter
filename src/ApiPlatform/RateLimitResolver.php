<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Core\ExpressionIdentityResolver;
use JacyImp\ApiPlatformRateLimiter\Core\IdentityExpressionEvaluator;
use JacyImp\ApiPlatformRateLimiter\Core\IntervalNormalizer;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitConditionEvaluator;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitDefinition;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitStrategyRegistry;
use JacyImp\ApiPlatformRateLimiter\Core\ResolvedRateLimit;
use JacyImp\ApiPlatformRateLimiter\Core\SharedRateLimitRegistry;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;
use JacyImp\ApiPlatformRateLimiter\Metadata\BypassRateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicBucket;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicCost;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\Identity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\IdentityExpression;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

/**
 * @internal
 */
final readonly class RateLimitResolver
{
    /**
     * @param array<string, RateLimitDefinition> $globalRateLimits
     */
    public function __construct(
        private RateLimitMetadataExtractor $metadataExtractor,
        private RateLimitProviderCollection $providerCollection,
        private IntervalNormalizer $intervalNormalizer,
        private SharedRateLimitRegistry $sharedRateLimitRegistry,
        private RateLimitStrategyRegistry $strategyRegistry,
        private array $globalRateLimits = [],
        private ?IdentityExpressionEvaluator $identityExpressionEvaluator = null,
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

        /** @var list<array{bucket: string, rateLimit: ResolvedRateLimit}> $resolved */
        $resolved = [];

        foreach ($rateLimits as $rateLimit) {
            $bucket = $this->resolveBucket($rateLimit, $operationKey);
            $resolved[] = [
                'bucket' => $bucket,
                'rateLimit' => $this->resolveRateLimit(
                    rateLimit: $rateLimit,
                    bucket: $bucket,
                ),
            ];
        }

        foreach ($this->globalRateLimits as $name => $globalRateLimit) {
            $bucket = sprintf('global:%s', $name);
            $resolved[] = [
                'bucket' => $bucket,
                'rateLimit' => new ResolvedRateLimit(
                    bucket: $bucket,
                    definition: $globalRateLimit,
                    identityResolver: $this->resolveIdentity(
                        $globalRateLimit->identity,
                    ),
                    condition: $globalRateLimit->when,
                ),
            ];
        }

        $bypasses = array_values(array_filter(
            $this->metadataExtractor->extractBypasses($operation),
            fn (BypassRateLimit $bypass): bool => $bypass->when === null
                || (new RateLimitConditionEvaluator($this->strategyRegistry))
                    ->matches($bypass->when),
        ));

        return array_values(array_map(
            static fn (array $item): ResolvedRateLimit => $item['rateLimit'],
            array_filter(
                $resolved,
                fn (array $item): bool => !$this->isBypassed(
                    $item['bucket'],
                    $bypasses,
                ),
            ),
        ));
    }

    /** @param list<BypassRateLimit> $bypasses */
    private function isBypassed(string $bucket, array $bypasses): bool
    {
        foreach ($bypasses as $bypass) {
            if ($bypass->bucket === null || $bypass->bucket === $bucket) {
                return true;
            }
        }

        return false;
    }

    private function resolveRateLimit(RateLimit $rateLimit, string $bucket,): ResolvedRateLimit
    {
        $definition = $this->resolveDefinition($rateLimit, $bucket);
        $identity = $rateLimit->identity ?? $definition->identity;
        $when = $rateLimit->when ?? $definition->when;

        return new ResolvedRateLimit(
            bucket: $rateLimit->bucket === null
                ? sprintf('operation:%s', $bucket)
                : sprintf('shared:%s', $bucket),
            definition: $definition,
            identityResolver: $this->resolveIdentity($identity),
            condition: $when,
            cost: $this->resolveCost($rateLimit),
        );
    }

    private function resolveIdentity(
        IdentityExpression|string|null $identity,
    ): ?IdentityResolverInterface {
        if ($identity === null) {
            return null;
        }

        if (is_string($identity)) {
            $identity = new Identity($identity);
        }

        $evaluator = $this->identityExpressionEvaluator
            ?? new IdentityExpressionEvaluator($this->strategyRegistry);

        return new ExpressionIdentityResolver($evaluator, $identity);
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
