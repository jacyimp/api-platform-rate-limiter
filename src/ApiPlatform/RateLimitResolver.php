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
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AllOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\RateLimitCondition;
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
    /** @param array<string, RateLimit> $globalRateLimits */
    public function __construct(
        private RateLimitMetadataExtractor $metadataExtractor,
        private RateLimitProviderCollection $providerCollection,
        private IntervalNormalizer $intervalNormalizer,
        private SharedRateLimitRegistry $sharedRateLimitRegistry,
        private RateLimitStrategyRegistry $strategyRegistry,
        private array $globalRateLimits = [],
        private ?IdentityExpressionEvaluator $identityExpressionEvaluator = null,
        private ?RateLimitConditionEvaluator $conditionEvaluator = null,
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

        /** @var list<array{aliases: list<string>, rateLimit: ResolvedRateLimit}> $resolved */
        $resolved = [];

        foreach ($rateLimits as $rateLimit) {
            $bucket = $this->resolveBucket($rateLimit, $operationKey);
            $resolvedRateLimit = $this->resolveRateLimit($rateLimit, $bucket);
            if ($resolvedRateLimit === null) {
                continue;
            }
            $resolved[] = [
                'aliases' => [$bucket, $resolvedRateLimit->bucket],
                'rateLimit' => $resolvedRateLimit,
            ];
        }

        foreach ($this->globalRateLimits as $name => $globalRateLimit) {
            $bucket = $this->resolveBucket($globalRateLimit, $name);
            $resolvedRateLimit = $this->resolveRateLimit(
                rateLimit: $globalRateLimit,
                bucket: $bucket,
                globalName: $name,
            );
            if ($resolvedRateLimit === null) {
                continue;
            }
            $resolved[] = [
                'aliases' => [$resolvedRateLimit->bucket],
                'rateLimit' => $resolvedRateLimit,
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
                    $item['aliases'],
                    $bypasses,
                ),
            ),
        ));
    }

    /**
     * @param list<string> $aliases
     * @param list<BypassRateLimit> $bypasses
     */
    private function isBypassed(array $aliases, array $bypasses): bool
    {
        foreach ($bypasses as $bypass) {
            if ($bypass->bucket === null || in_array($bypass->bucket, $aliases, true)) {
                return true;
            }
        }

        return false;
    }

    private function resolveRateLimit(
        RateLimit $rateLimit,
        string $bucket,
        ?string $globalName = null,
    ): ?ResolvedRateLimit {
        $declaration = $this->resolveDeclaration($rateLimit, $bucket);
        if (!$this->conditionMatches($declaration->when)) {
            return null;
        }

        $definition = $this->resolveDefinition($declaration);

        return new ResolvedRateLimit(
            bucket: $this->resolvedBucketName($rateLimit, $bucket, $globalName),
            definition: $definition,
            identityResolver: $this->resolveIdentity($declaration->identity),
            cost: $this->resolveCost($declaration),
        );
    }

    private function resolveDeclaration(RateLimit $rateLimit, string $bucket): RateLimit
    {
        if ($rateLimit->limit !== null) {
            return $rateLimit;
        }

        $configured = $this->sharedRateLimitRegistry->get($bucket);

        return new RateLimit(
            limit: $configured->limit,
            interval: $configured->interval,
            policy: $configured->policy,
            identity: $rateLimit->identity ?? $configured->identity,
            when: $this->composeConditions($configured->when, $rateLimit->when),
            bucket: $rateLimit->bucket,
            cost: $this->resolveCost($configured) * $this->resolveCost($rateLimit),
        );
    }

    private function composeConditions(
        ?RateLimitCondition $configured,
        ?RateLimitCondition $reference,
    ): ?RateLimitCondition {
        if ($configured === null) {
            return $reference;
        }

        if ($reference === null) {
            return $configured;
        }

        return new AllOf([$configured, $reference]);
    }

    private function conditionMatches(?RateLimitCondition $condition): bool
    {
        if ($condition === null) {
            return true;
        }

        $evaluator = $this->conditionEvaluator
            ?? new RateLimitConditionEvaluator($this->strategyRegistry);

        return $evaluator->matches($condition);
    }

    private function resolvedBucketName(
        RateLimit $rateLimit,
        string $bucket,
        ?string $globalName,
    ): string {
        if ($globalName !== null) {
            return $rateLimit->bucket === null
                ? sprintf('global:%s', $globalName)
                : sprintf('global:%s:%s', $globalName, $bucket);
        }

        return $rateLimit->bucket === null
            ? sprintf('operation:%s', $bucket)
            : sprintf('shared:%s', $bucket);
    }

    private function resolveIdentity(
        IdentityExpression|string|null $identity,
    ): ?IdentityResolverInterface {
        if ($identity === null) {
            return null;
        }

        if (is_string($identity)) {
            if (trim($identity) === '') {
                throw new InvalidRateLimitException(
                    'Identity resolver service ID cannot be empty.',
                );
            }

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

    private function resolveDefinition(RateLimit $rateLimit): RateLimitDefinition
    {
        $limit = $rateLimit->limit instanceof DynamicLimit
            ? $this->strategyRegistry
                ->limitResolver($rateLimit->limit->resolver)
                ->resolve()
            : $rateLimit->limit;
        if ($limit === null) {
            throw new \LogicException('Resolved rate limit declaration must have a limit.');
        }
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
