<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use InvalidArgumentException;
use Jacyimp\ApiPlatformRateLimiter\Core\IntervalNormalizer;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitDefinition;
use Jacyimp\ApiPlatformRateLimiter\Core\ResolvedRateLimit;
use Jacyimp\ApiPlatformRateLimiter\Core\SharedRateLimitRegistry;
use Jacyimp\ApiPlatformRateLimiter\Metadata\OperationRateLimit;
use Jacyimp\ApiPlatformRateLimiter\Metadata\SharedRateLimit;

final readonly class RateLimitResolver
{
    public function __construct(
        private RateLimitMetadataExtractor $metadataExtractor,
        private IntervalNormalizer $intervalNormalizer,
        private SharedRateLimitRegistry $sharedRateLimitRegistry,
    ) {
    }

    /**
     * @return list<ResolvedRateLimit>
     */
    public function resolve(
        Operation $operation,
        string $operationKey,
    ): array {
        $resolved = [];

        foreach ($this->metadataExtractor->extract($operation) as $rateLimit) {
            $resolved[] = match (true) {
                $rateLimit instanceof OperationRateLimit => $this->resolveOperation(
                    rateLimit: $rateLimit,
                    operationKey: $operationKey,
                ),
                $rateLimit instanceof SharedRateLimit => $this->resolveShared(
                    $rateLimit,
                ),
            };
        }

        return $resolved;
    }

    private function resolveOperation(
        OperationRateLimit $rateLimit,
        string $operationKey,
    ): ResolvedRateLimit {
        if (trim($operationKey) === '') {
            throw new InvalidArgumentException(
                'Operation key cannot be empty.',
            );
        }

        return new ResolvedRateLimit(
            bucket: sprintf('operation:%s', $operationKey),
            definition: new RateLimitDefinition(
                limit: $rateLimit->limit,
                intervalSeconds: $this->intervalNormalizer->normalize(
                    $rateLimit->interval,
                ),
                policy: $rateLimit->policy,
            ),
        );
    }

    private function resolveShared(
        SharedRateLimit $rateLimit,
    ): ResolvedRateLimit {
        return new ResolvedRateLimit(
            bucket: sprintf('shared:%s', $rateLimit->bucket),
            definition: $this->sharedRateLimitRegistry->get(
                $rateLimit->bucket,
            ),
        );
    }
}
