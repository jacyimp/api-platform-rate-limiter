<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use InvalidArgumentException;
use JacyImp\ApiPlatformRateLimiter\Core\IntervalNormalizer;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitDefinition;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitStrategyRegistry;
use JacyImp\ApiPlatformRateLimiter\Core\ResolvedRateLimit;
use JacyImp\ApiPlatformRateLimiter\Core\SharedRateLimitRegistry;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\SharedRateLimit;

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

        return $resolved;
    }

    private function resolveRateLimit(
        RateLimit|SharedRateLimit $rateLimit,
        string $operationKey,
    ): ResolvedRateLimit {
        if ($rateLimit instanceof RateLimit) {
            return $this->resolveOperation(
                rateLimit: $rateLimit,
                operationKey: $operationKey,
            );
        }

        return $this->resolveShared($rateLimit);
    }

    private function resolveOperation(
        RateLimit $rateLimit,
        string $operationKey,
    ): ResolvedRateLimit {
        if (trim($operationKey) === '') {
            throw new InvalidArgumentException(
                'Operation key cannot be empty.',
            );
        }

        return new ResolvedRateLimit(
            bucket: sprintf(
                'operation:%s',
                $operationKey,
            ),
            definition: new RateLimitDefinition(
                limit: $rateLimit->limit,
                intervalSeconds: $this->intervalNormalizer->normalize(
                    $rateLimit->interval,
                ),
                policy: $rateLimit->policy,
            ),
            identityResolver: $rateLimit->identityResolver === null
                ? null
                : $this->strategyRegistry->identityResolver(
                    $rateLimit->identityResolver,
                ),
            condition: $rateLimit->when === null
                ? null
                : $this->strategyRegistry->condition($rateLimit->when),
        );
    }

    private function resolveShared(
        SharedRateLimit $rateLimit,
    ): ResolvedRateLimit {
        $definition = $this->sharedRateLimitRegistry->get(
            $rateLimit->bucket,
        );

        return new ResolvedRateLimit(
            bucket: sprintf(
                'shared:%s',
                $rateLimit->bucket,
            ),
            definition: $definition,
            identityResolver: $definition->identityResolver === null
                ? null
                : $this->strategyRegistry->identityResolver(
                    $definition->identityResolver,
                ),
            condition: $definition->when === null
                ? null
                : $this->strategyRegistry->condition($definition->when),
        );
    }
}
