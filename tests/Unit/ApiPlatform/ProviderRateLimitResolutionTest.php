<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\ApiPlatform;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitMetadataExtractor;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitProviderCollection;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitResolver;
use JacyImp\ApiPlatformRateLimiter\Contract\BucketResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\CostResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\LimitResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitBypassInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitProviderInterface;
use JacyImp\ApiPlatformRateLimiter\Core\IntervalNormalizer;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitEnforcer;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimiterInterface;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitStrategyRegistry;
use JacyImp\ApiPlatformRateLimiter\Core\SharedRateLimitRegistry;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;
use JacyImp\ApiPlatformRateLimiter\Metadata\BypassRateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AllOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Condition;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicBucket;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicCost;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\CompositeIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\FirstAvailableIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\Identity;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(RateLimitResolver::class)]
final class ProviderRateLimitResolutionTest extends TestCase
{
    #[Test]
    public function itResolvesEveryProviderCapabilityThroughTheCommonPipeline(): void
    {
        $bucketResolver = new class implements BucketResolverInterface {
            public function resolve(): string
            {
                return 'customers';
            }
        };
        $limitResolver = new class implements LimitResolverInterface {
            public function resolve(): int
            {
                return 42;
            }
        };
        $costResolver = new class implements CostResolverInterface {
            public function resolve(): int
            {
                return 3;
            }
        };
        $firstIdentity = new class implements IdentityResolverInterface {
            public function resolve(): string
            {
                return 'tenant';
            }
        };
        $secondIdentity = new class implements IdentityResolverInterface {
            public function resolve(): string
            {
                return 'user';
            }
        };
        $condition = new class implements RateLimitConditionInterface {
            public function matches(): bool
            {
                return true;
            }
        };
        $provided = new RateLimit(
            limit: new DynamicLimit($limitResolver::class),
            interval: '2 minutes',
            bucket: new DynamicBucket($bucketResolver::class),
            cost: new DynamicCost($costResolver::class),
            identity: new CompositeIdentity([
                new Identity($firstIdentity::class),
                new Identity($secondIdentity::class),
            ]),
            when: new AllOf([
                new Condition($condition::class),
                new Condition($condition::class),
            ]),
            policy: RateLimitPolicy::FIXED_WINDOW,
        );

        $resolved = $this->resolver(
            providers: [$this->provider([$provided])],
            identities: [$firstIdentity, $secondIdentity],
            conditions: [$condition],
            bucketResolvers: [$bucketResolver],
            limitResolvers: [$limitResolver],
            costResolvers: [$costResolver],
        )->resolve(new Get(), 'orders_get');

        self::assertCount(1, $resolved);
        self::assertSame('shared:customers', $resolved[0]->bucket);
        self::assertSame(42, $resolved[0]->definition->limit);
        self::assertSame(120, $resolved[0]->definition->intervalSeconds);
        self::assertSame(RateLimitPolicy::FIXED_WINDOW, $resolved[0]->definition->policy);
        self::assertSame(3, $resolved[0]->cost);
        self::assertSame(
            'composite:6:tenant4:user',
            $resolved[0]->identityResolver?->resolve(),
        );
    }

    #[Test]
    public function itResolvesAProviderConfiguredBucketReference(): void
    {
        $configured = new RateLimit(
            limit: 100,
            interval: '1 hour',
            cost: 2,
            policy: RateLimitPolicy::FIXED_WINDOW,
        );
        $provided = new RateLimit(bucket: 'catalog', cost: 3);

        $resolved = $this->resolver(
            shared: ['catalog' => $configured],
            providers: [$this->provider([$provided])],
        )->resolve(new Get(), 'products_get');

        self::assertSame('shared:catalog', $resolved[0]->bucket);
        self::assertSame(100, $resolved[0]->definition->limit);
        self::assertSame(RateLimitPolicy::FIXED_WINDOW, $resolved[0]->definition->policy);
        self::assertSame(6, $resolved[0]->cost);
    }

    #[Test]
    public function itTreatsAnExplicitProviderBucketAsShared(): void
    {
        $provided = new RateLimit(
            limit: 25,
            interval: '1 minute',
            bucket: 'checkout',
            cost: 2,
        );

        $resolved = $this->resolver(
            providers: [$this->provider([$provided])],
        )->resolve(new Get(), 'orders_get');

        self::assertSame('shared:checkout', $resolved[0]->bucket);
        self::assertSame(25, $resolved[0]->definition->limit);
        self::assertSame(2, $resolved[0]->cost);
    }

    #[Test]
    public function itResolvesAProviderFallbackIdentity(): void
    {
        $unavailable = new class implements IdentityResolverInterface {
            public function resolve(): ?string
            {
                return null;
            }
        };
        $available = $this->identity('api-key');
        $provided = new RateLimit(
            limit: 10,
            interval: '1 minute',
            identity: new FirstAvailableIdentity([
                new Identity($unavailable::class),
                new Identity($available::class),
            ]),
        );

        $resolved = $this->resolver(
            providers: [$this->provider([$provided])],
            identities: [$unavailable, $available],
        )->resolve(new Get(), 'orders_get');

        self::assertSame('api-key', $resolved[0]->identityResolver?->resolve());
    }

    #[Test]
    public function itCombinesMetadataMultipleProvidersAndAnEmptyProviderInOrder(): void
    {
        $metadata = new RateLimit(limit: 10, interval: '1 minute');
        $first = new RateLimit(limit: 20, interval: '1 minute', bucket: 'first');
        $second = new RateLimit(limit: 30, interval: '1 minute', bucket: 'second');

        $resolved = $this->resolver(providers: [
            $this->provider([$first]),
            $this->provider([]),
            $this->provider([$second]),
        ])->resolve(new Get(extraProperties: [
            RateLimit::class => $metadata,
        ]), 'orders_get');

        self::assertSame(
            ['operation:orders_get', 'shared:first', 'shared:second'],
            array_map(static fn ($limit): string => $limit->bucket, $resolved),
        );
    }

    #[Test]
    public function itKeepsDuplicateMetadataAndProviderDeclarations(): void
    {
        $duplicate = new RateLimit(limit: 10, interval: '1 minute');

        $resolved = $this->resolver(
            providers: [$this->provider([$duplicate])],
        )->resolve(new Get(extraProperties: [
            RateLimit::class => $duplicate,
        ]), 'orders_get');

        self::assertCount(2, $resolved);
        self::assertEquals($resolved[0], $resolved[1]);
    }

    #[Test]
    public function itBypassesAProviderLimitByItsFinalDynamicBucket(): void
    {
        $bucketResolver = new class implements BucketResolverInterface {
            public function resolve(): string
            {
                return 'customer:premium';
            }
        };
        $provided = new RateLimit(
            limit: 10,
            interval: '1 minute',
            bucket: new DynamicBucket($bucketResolver::class),
        );

        $resolved = $this->resolver(
            providers: [$this->provider([$provided])],
            bucketResolvers: [$bucketResolver],
        )->resolve(new Get(extraProperties: [
            BypassRateLimit::class => new BypassRateLimit(bucket: 'shared:customer:premium'),
        ]), 'orders_get');

        self::assertSame([], $resolved);
    }

    #[Test]
    public function itBypassesAllProviderLimitsWithAnUnscopedBypass(): void
    {
        $resolved = $this->resolver(providers: [
            $this->provider([new RateLimit(limit: 10, interval: '1 minute')]),
        ])->resolve(new Get(extraProperties: [
            BypassRateLimit::class => new BypassRateLimit(),
        ]), 'orders_get');

        self::assertSame([], $resolved);
    }

    #[Test]
    public function itGloballyBypassesProviderLimitsDuringEnforcement(): void
    {
        $resolved = $this->resolver(providers: [
            $this->provider([new RateLimit(limit: 10, interval: '1 minute')]),
        ])->resolve(new Get(), 'orders_get');
        $rateLimiter = self::createMock(RateLimiterInterface::class);
        $rateLimiter->expects(self::never())->method('consume');
        $bypass = self::createStub(RateLimitBypassInterface::class);
        $bypass->method('shouldBypass')->willReturn(true);

        $result = (new RateLimitEnforcer(
            rateLimiter: $rateLimiter,
            identityResolver: $this->identity('user'),
            bypass: $bypass,
            eventDispatcher: new EventDispatcher(),
        ))->enforce($resolved);

        self::assertSame([], $result->consumptions);
    }

    #[Test]
    public function itPropagatesProviderExceptions(): void
    {
        $provider = new class implements RateLimitProviderInterface {
            /** @return iterable<RateLimit> */
            public function provide(Operation $operation): iterable
            {
                throw new \RuntimeException('Provider failed.');
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Provider failed.');

        $this->resolver(providers: [$provider])->resolve(new Get(), 'orders_get');
    }

    #[Test]
    public function itRejectsAnInvalidDynamicallyResolvedProviderLimit(): void
    {
        $limitResolver = new class implements LimitResolverInterface {
            public function resolve(): int
            {
                return 0;
            }
        };
        $provided = new RateLimit(
            limit: new DynamicLimit($limitResolver::class),
            interval: '1 minute',
        );

        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage('Rate limit must be greater than zero.');

        $this->resolver(
            providers: [$this->provider([$provided])],
            limitResolvers: [$limitResolver],
        )->resolve(new Get(), 'orders_get');
    }

    /** @param list<RateLimit> $limits */
    private function provider(array $limits): RateLimitProviderInterface
    {
        return new class ($limits) implements RateLimitProviderInterface {
            /** @param list<RateLimit> $limits */
            public function __construct(private readonly array $limits)
            {
            }

            /** @return iterable<RateLimit> */
            public function provide(Operation $operation): iterable
            {
                return $this->limits;
            }
        };
    }

    private function identity(string $value): IdentityResolverInterface
    {
        return new class ($value) implements IdentityResolverInterface {
            public function __construct(private readonly string $value)
            {
            }

            public function resolve(): string
            {
                return $this->value;
            }
        };
    }

    /**
     * @param array<string, RateLimit> $shared
     * @param list<RateLimitProviderInterface> $providers
     * @param list<IdentityResolverInterface> $identities
     * @param list<RateLimitConditionInterface> $conditions
     * @param list<BucketResolverInterface> $bucketResolvers
     * @param list<LimitResolverInterface> $limitResolvers
     * @param list<CostResolverInterface> $costResolvers
     */
    private function resolver(
        array $shared = [],
        array $providers = [],
        array $identities = [],
        array $conditions = [],
        array $bucketResolvers = [],
        array $limitResolvers = [],
        array $costResolvers = [],
    ): RateLimitResolver {
        $registry = new RateLimitStrategyRegistry(
            $identities,
            $conditions,
            $bucketResolvers,
            $limitResolvers,
            $costResolvers,
        );

        return new RateLimitResolver(
            metadataExtractor: new RateLimitMetadataExtractor(),
            providerCollection: new RateLimitProviderCollection($providers),
            intervalNormalizer: new IntervalNormalizer(),
            sharedRateLimitRegistry: new SharedRateLimitRegistry($shared),
            strategyRegistry: $registry,
        );
    }
}
