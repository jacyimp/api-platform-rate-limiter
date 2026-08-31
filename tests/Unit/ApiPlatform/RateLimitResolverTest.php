<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\ApiPlatform;

use ApiPlatform\Metadata\Get;
use InvalidArgumentException;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitMetadataExtractor;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitProviderCollection;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitResolver;
use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitProviderInterface;
use JacyImp\ApiPlatformRateLimiter\Core\IntervalNormalizer;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitDefinition;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitStrategyRegistry;
use JacyImp\ApiPlatformRateLimiter\Core\SharedRateLimitRegistry;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use JacyImp\ApiPlatformRateLimiter\Metadata\SharedRateLimit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitResolver::class)]
final class RateLimitResolverTest extends TestCase
{
    #[Test]
    public function itResolvesRateLimit(): void
    {
        $resolver = $this->resolver();

        $operation = new Get(
            extraProperties: [
                RateLimit::class => new RateLimit(
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
        self::assertSame(
            100,
            $resolved[0]->definition->limit,
        );
        self::assertSame(
            60,
            $resolved[0]->definition->intervalSeconds,
        );
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
                SharedRateLimit::class => new SharedRateLimit(
                    'catalog',
                ),
            ],
        );

        $resolved = $resolver->resolve(
            operation: $operation,
            operationKey: 'product_get',
        );

        self::assertCount(1, $resolved);
        self::assertSame(
            'shared:catalog',
            $resolved[0]->bucket,
        );
        self::assertSame(
            $definition,
            $resolved[0]->definition,
        );
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
                RateLimit::class => new RateLimit(
                    limit: 100,
                    interval: '1 minute',
                ),
                SharedRateLimit::class => new SharedRateLimit(
                    'catalog',
                ),
            ],
        );

        $resolved = $resolver->resolve(
            operation: $operation,
            operationKey: 'product_get',
        );

        self::assertCount(2, $resolved);
        self::assertSame(
            'operation:product_get',
            $resolved[0]->bucket,
        );
        self::assertSame(
            'shared:catalog',
            $resolved[1]->bucket,
        );
    }

    #[Test]
    public function itResolvesProviderRateLimits(): void
    {
        $providedRateLimit = new RateLimit(
            limit: 25,
            interval: '1 minute',
        );

        $provider = self::createStub(
            RateLimitProviderInterface::class,
        );

        $provider
            ->method('provide')
            ->willReturn([
                $providedRateLimit,
            ]);

        $resolver = $this->resolver(
            providers: [
                $provider,
            ],
        );

        $resolved = $resolver->resolve(
            operation: new Get(),
            operationKey: 'product_get',
        );

        self::assertCount(1, $resolved);
        self::assertSame(
            'operation:product_get',
            $resolved[0]->bucket,
        );
        self::assertSame(
            25,
            $resolved[0]->definition->limit,
        );
    }

    #[Test]
    public function itResolvesOperationSpecificStrategies(): void
    {
        $identityResolver = self::createStub(
            IdentityResolverInterface::class,
        );
        $condition = self::createStub(RateLimitConditionInterface::class);

        $resolver = $this->resolver(
            identityResolvers: [$identityResolver],
            conditions: [$condition],
        );

        $resolved = $resolver->resolve(
            operation: new Get(extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 5,
                    interval: '1 minute',
                    identityResolver: $identityResolver::class,
                    when: $condition::class,
                ),
            ]),
            operationKey: 'otp_post',
        );

        self::assertSame($identityResolver, $resolved[0]->identityResolver);
        self::assertSame($condition, $resolved[0]->condition);
    }

    #[Test]
    public function itResolvesSharedLimitStrategies(): void
    {
        $identityResolver = self::createStub(
            IdentityResolverInterface::class,
        );
        $condition = self::createStub(RateLimitConditionInterface::class);

        $definition = new RateLimitDefinition(
            limit: 5,
            intervalSeconds: 60,
            policy: RateLimitPolicy::SLIDING_WINDOW,
            identityResolver: $identityResolver::class,
            when: $condition::class,
        );

        $resolver = $this->resolver(
            shared: ['otp' => $definition],
            identityResolvers: [$identityResolver],
            conditions: [$condition],
        );

        $resolved = $resolver->resolve(
            operation: new Get(extraProperties: [
                SharedRateLimit::class => new SharedRateLimit('otp'),
            ]),
            operationKey: 'otp_post',
        );

        self::assertSame($identityResolver, $resolved[0]->identityResolver);
        self::assertSame($condition, $resolved[0]->condition);
    }

    #[Test]
    public function itResolvesMetadataBeforeProviderRateLimits(): void
    {
        $provider = self::createStub(
            RateLimitProviderInterface::class,
        );

        $provider
            ->method('provide')
            ->willReturn([
                new SharedRateLimit('catalog'),
            ]);

        $sharedDefinition = new RateLimitDefinition(
            limit: 1_000,
            intervalSeconds: 3_600,
            policy: RateLimitPolicy::SLIDING_WINDOW,
        );

        $resolver = $this->resolver(
            shared: [
                'catalog' => $sharedDefinition,
            ],
            providers: [
                $provider,
            ],
        );

        $operation = new Get(
            extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 100,
                    interval: '1 minute',
                ),
            ],
        );

        $resolved = $resolver->resolve(
            operation: $operation,
            operationKey: 'product_get',
        );

        self::assertCount(2, $resolved);
        self::assertSame(
            'operation:product_get',
            $resolved[0]->bucket,
        );
        self::assertSame(
            'shared:catalog',
            $resolved[1]->bucket,
        );
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
    public function itRejectsEmptyOperationKeyForRateLimit(): void
    {
        $operation = new Get(
            extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 100,
                    interval: '1 minute',
                ),
            ],
        );

        $this->expectException(
            InvalidArgumentException::class,
        );

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
     * @param list<RateLimitProviderInterface> $providers
     * @param list<IdentityResolverInterface> $identityResolvers
     * @param list<RateLimitConditionInterface> $conditions
     */
    private function resolver(
        array $shared = [],
        array $providers = [],
        array $identityResolvers = [],
        array $conditions = [],
    ): RateLimitResolver {
        return new RateLimitResolver(
            metadataExtractor: new RateLimitMetadataExtractor(),
            providerCollection: new RateLimitProviderCollection(
                $providers,
            ),
            intervalNormalizer: new IntervalNormalizer(),
            sharedRateLimitRegistry: new SharedRateLimitRegistry(
                $shared,
            ),
            strategyRegistry: new RateLimitStrategyRegistry(
                $identityResolvers,
                $conditions,
            ),
        );
    }
}
