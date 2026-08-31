<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Tests\Unit\ApiPlatform;

use ApiPlatform\Metadata\Get;
use InvalidArgumentException;
use Jacyimp\ApiPlatformRateLimiter\ApiPlatform\RateLimitMetadataExtractor;
use Jacyimp\ApiPlatformRateLimiter\ApiPlatform\RateLimitResolver;
use Jacyimp\ApiPlatformRateLimiter\Core\IntervalNormalizer;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitDefinition;
use Jacyimp\ApiPlatformRateLimiter\Core\SharedRateLimitRegistry;
use Jacyimp\ApiPlatformRateLimiter\Metadata\OperationRateLimit;
use Jacyimp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use Jacyimp\ApiPlatformRateLimiter\Metadata\SharedRateLimit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitResolver::class)]
final class RateLimitResolverTest extends TestCase
{
    #[Test]
    public function itResolvesOperationRateLimit(): void
    {
        $resolver = $this->resolver();

        $operation = new Get(
            extraProperties: [
                OperationRateLimit::class => new OperationRateLimit(
                    limit: 100,
                    interval: '1 minute',
                ),
            ],
        );

        $resolved = $resolver->resolve(
            operation: $operation,
            operationKey: 'product_get',
        );

        self::assertCount(1, $resolved);
        self::assertSame(
            'operation:product_get',
            $resolved[0]->bucket,
        );
        self::assertSame(100, $resolved[0]->definition->limit);
        self::assertSame(60, $resolved[0]->definition->intervalSeconds);
    }

    #[Test]
    public function itResolvesSharedRateLimit(): void
    {
        $definition = new RateLimitDefinition(
            limit: 1_000,
            intervalSeconds: 3_600,
            policy: RateLimitPolicy::SLIDING_WINDOW,
        );

        $resolver = $this->resolver([
            'catalog' => $definition,
        ]);

        $operation = new Get(
            extraProperties: [
                SharedRateLimit::class => new SharedRateLimit('catalog'),
            ],
        );

        $resolved = $resolver->resolve(
            operation: $operation,
            operationKey: 'product_get',
        );

        self::assertCount(1, $resolved);
        self::assertSame('shared:catalog', $resolved[0]->bucket);
        self::assertSame($definition, $resolved[0]->definition);
    }

    #[Test]
    public function itResolvesOperationAndSharedRateLimits(): void
    {
        $sharedDefinition = new RateLimitDefinition(
            limit: 1_000,
            intervalSeconds: 3_600,
            policy: RateLimitPolicy::FIXED_WINDOW,
        );

        $resolver = $this->resolver([
            'catalog' => $sharedDefinition,
        ]);

        $operation = new Get(
            extraProperties: [
                OperationRateLimit::class => new OperationRateLimit(
                    limit: 100,
                    interval: '1 minute',
                ),
                SharedRateLimit::class => new SharedRateLimit('catalog'),
            ],
        );

        $resolved = $resolver->resolve(
            operation: $operation,
            operationKey: 'product_get',
        );

        self::assertCount(2, $resolved);
        self::assertSame('operation:product_get', $resolved[0]->bucket);
        self::assertSame('shared:catalog', $resolved[1]->bucket);
    }

    #[Test]
    public function itReturnsEmptyListWithoutRateLimits(): void
    {
        self::assertSame(
            [],
            $this->resolver()->resolve(
                operation: new Get(),
                operationKey: 'product_get',
            ),
        );
    }

    #[Test]
    public function itRejectsEmptyOperationKeyForOperationRateLimit(): void
    {
        $operation = new Get(
            extraProperties: [
                OperationRateLimit::class => new OperationRateLimit(
                    limit: 100,
                    interval: '1 minute',
                ),
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Operation key cannot be empty.',
        );

        $this->resolver()->resolve(
            operation: $operation,
            operationKey: '',
        );
    }

    /**
     * @param array<string, RateLimitDefinition> $shared
     */
    private function resolver(array $shared = []): RateLimitResolver
    {
        return new RateLimitResolver(
            metadataExtractor: new RateLimitMetadataExtractor(),
            intervalNormalizer: new IntervalNormalizer(),
            sharedRateLimitRegistry: new SharedRateLimitRegistry($shared),
        );
    }
}
