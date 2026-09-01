<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\ApiPlatform;

use ApiPlatform\Metadata\Get;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitMetadataExtractor;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitProviderCollection;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitResolver;
use JacyImp\ApiPlatformRateLimiter\Contract\BucketResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\CostResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\LimitResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitProviderInterface;
use JacyImp\ApiPlatformRateLimiter\Core\IntervalNormalizer;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitConditionEvaluator;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitDefinition;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitStrategyRegistry;
use JacyImp\ApiPlatformRateLimiter\Core\ResolvedRateLimit;
use JacyImp\ApiPlatformRateLimiter\Core\SharedRateLimitRegistry;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;
use JacyImp\ApiPlatformRateLimiter\Metadata\BypassRateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AllOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Condition;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicBucket;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicCost;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\Identity;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
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
        $definition = new RateLimit(
            limit: 1_000,
            interval: '1 hour',
            policy: RateLimitPolicy::SLIDING_WINDOW,
        );

        $resolver = $this->resolver([
            'catalog' => $definition,
        ]);

        $operation = new Get(
            extraProperties: [
                RateLimit::class => new RateLimit(bucket: 'catalog'),
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
        self::assertSame(1_000, $resolved[0]->definition->limit);
    }

    #[Test]
    public function itResolvesConfiguredBucketFromRateLimit(): void
    {
        $definition = new RateLimit(
            limit: 1_000,
            interval: '1 hour',
            policy: RateLimitPolicy::FIXED_WINDOW,
        );
        $resolved = $this->resolver(['catalog' => $definition])->resolve(
            operation: new Get(extraProperties: [
                RateLimit::class => new RateLimit(bucket: 'catalog'),
            ]),
            operationKey: 'product_get',
        );

        self::assertSame('shared:catalog', $resolved[0]->bucket);
        self::assertSame(RateLimitPolicy::FIXED_WINDOW, $resolved[0]->definition->policy);
    }

    #[Test]
    public function itResolvesInlineSharedBucket(): void
    {
        $resolved = $this->resolver()->resolve(
            operation: new Get(extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 50,
                    interval: '1 minute',
                    bucket: 'catalog',
                ),
            ]),
            operationKey: 'product_get',
        );

        self::assertSame('shared:catalog', $resolved[0]->bucket);
        self::assertSame(50, $resolved[0]->definition->limit);
    }

    #[Test]
    public function itResolvesDynamicBucketAndLimit(): void
    {
        $bucketResolver = new class implements BucketResolverInterface {
            public function resolve(): string
            {
                return 'customer-tier';
            }
        };
        $limitResolver = new class implements LimitResolverInterface {
            public function resolve(): int
            {
                return 75;
            }
        };
        $resolved = $this->resolver(
            bucketResolvers: [$bucketResolver],
            limitResolvers: [$limitResolver],
        )->resolve(
            operation: new Get(extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: new DynamicLimit($limitResolver::class),
                    interval: '1 minute',
                    bucket: new DynamicBucket($bucketResolver::class),
                ),
            ]),
            operationKey: 'product_get',
        );

        self::assertSame('shared:customer-tier', $resolved[0]->bucket);
        self::assertSame(75, $resolved[0]->definition->limit);
    }

    #[Test]
    public function itResolvesStaticAndDynamicCosts(): void
    {
        $costResolver = new class implements CostResolverInterface {
            public function resolve(): int
            {
                return 4;
            }
        };

        $resolved = $this->resolver(costResolvers: [$costResolver])->resolve(
            operation: new Get(extraProperties: [
                RateLimit::class => [
                    new RateLimit(limit: 10, interval: '1 minute', cost: 2,),
                    new RateLimit(
                        limit: 20,
                        interval: '1 minute',
                        cost: new DynamicCost($costResolver::class),
                    ),
                ],
            ]),
            operationKey: 'product_get',
        );

        self::assertSame(2, $resolved[0]->cost);
        self::assertSame(4, $resolved[1]->cost);
    }

    #[Test]
    public function itRejectsInvalidResolvedDynamicCost(): void
    {
        $costResolver = new class implements CostResolverInterface {
            public function resolve(): int
            {
                return 0;
            }
        };

        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(
            'Resolved rate limit cost must be greater than zero.',
        );

        $this->resolver(costResolvers: [$costResolver])->resolve(
            operation: new Get(extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 10,
                    interval: '1 minute',
                    cost: new DynamicCost($costResolver::class),
                ),
            ]),
            operationKey: 'product_get',
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
                RateLimit::class => [
                    new RateLimit(limit: 100, interval: '1 minute',),
                    new RateLimit(bucket: 'catalog'),
                ],
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
        $condition->method('matches')->willReturn(true);

        $resolver = $this->resolver(
            identityResolvers: [$identityResolver],
            conditions: [$condition],
        );

        $resolved = $resolver->resolve(
            operation: new Get(extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 5,
                    interval: '1 minute',
                    identity: new Identity($identityResolver::class),
                    when: new Condition($condition::class),
                ),
            ]),
            operationKey: 'otp_post',
        );

        self::assertSame(
            $identityResolver->resolve(),
            $resolved[0]->identityResolver?->resolve(),
        );
        self::assertCount(1, $resolved);
    }

    #[Test]
    public function itResolvesSharedLimitStrategies(): void
    {
        $identityResolver = self::createStub(
            IdentityResolverInterface::class,
        );
        $condition = self::createStub(RateLimitConditionInterface::class);
        $condition->method('matches')->willReturn(true);

        $definition = new RateLimit(
            limit: 5,
            interval: '1 minute',
            policy: RateLimitPolicy::SLIDING_WINDOW,
            identity: new Identity($identityResolver::class),
            when: new Condition($condition::class),
        );

        $resolver = $this->resolver(
            shared: ['otp' => $definition],
            identityResolvers: [$identityResolver],
            conditions: [$condition],
        );

        $resolved = $resolver->resolve(
            operation: new Get(extraProperties: [
                RateLimit::class => new RateLimit(bucket: 'otp'),
            ]),
            operationKey: 'otp_post',
        );

        self::assertSame(
            $identityResolver->resolve(),
            $resolved[0]->identityResolver?->resolve(),
        );
        self::assertCount(1, $resolved);
    }

    #[Test]
    public function itOverridesSharedLimitStrategiesFromMetadata(): void
    {
        $identityResolver = self::createStub(
            IdentityResolverInterface::class,
        );
        $condition = self::createStub(RateLimitConditionInterface::class);
        $condition->method('matches')->willReturn(true);

        $resolver = $this->resolver(
            shared: [
                'otp' => new RateLimit(
                    limit: 5,
                    interval: '1 minute',
                    policy: RateLimitPolicy::SLIDING_WINDOW,
                    identity: new Identity('default.identity'),
                    when: new Condition($condition::class),
                ),
            ],
            identityResolvers: [$identityResolver],
            conditions: [$condition],
        );

        $resolved = $resolver->resolve(
            operation: new Get(extraProperties: [
                RateLimit::class => new RateLimit(
                    bucket: 'otp',
                    identity: new Identity($identityResolver::class),
                    when: new Condition($condition::class),
                ),
            ]),
            operationKey: 'otp_post',
        );

        self::assertSame(
            $identityResolver->resolve(),
            $resolved[0]->identityResolver?->resolve(),
        );
        self::assertCount(1, $resolved);
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
                new RateLimit(bucket: 'catalog'),
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
    public function itResolvesOneNamedGlobalRateLimitForEveryOperation(): void
    {
        $global = new RateLimit(
            limit: 1_000,
            interval: '1 hour',
            policy: RateLimitPolicy::FIXED_WINDOW,
        );

        $resolved = $this->resolver(globals: ['burst' => $global])->resolve(
            operation: new Get(),
            operationKey: 'product_get',
        );

        self::assertCount(1, $resolved);
        self::assertSame('global:burst', $resolved[0]->bucket);
        self::assertSame(1_000, $resolved[0]->definition->limit);
    }

    #[Test]
    public function itResolvesMultipleGlobalsInConfigurationOrder(): void
    {
        $burst = new RateLimit(100, '1 minute');
        $daily = new RateLimit(10_000, '1 day');

        $resolved = $this->resolver(globals: [
            'burst' => $burst,
            'daily' => $daily,
        ])->resolve(new Get(), 'product_get');

        self::assertSame(
            ['global:burst', 'global:daily'],
            array_map(
                static fn (ResolvedRateLimit $limit): string => $limit->bucket,
                $resolved,
            ),
        );
        self::assertSame(100, $resolved[0]->definition->limit);
        self::assertSame(10_000, $resolved[1]->definition->limit);
    }

    #[Test]
    public function itResolvesDifferentIdentitiesAndConditionsForEachGlobal(): void
    {
        $burstIdentity = new class implements IdentityResolverInterface {
            public function resolve(): string
            {
                return 'burst';
            }
        };
        $dailyIdentity = new class implements IdentityResolverInterface {
            public function resolve(): string
            {
                return 'daily';
            }
        };
        $burstCondition = new class implements RateLimitConditionInterface {
            public function matches(): bool
            {
                return true;
            }
        };
        $dailyCondition = new class implements RateLimitConditionInterface {
            public function matches(): bool
            {
                return false;
            }
        };

        $resolved = $this->resolver(
            globals: [
                'burst' => new RateLimit(
                    100,
                    '1 minute',
                    identity: new Identity($burstIdentity::class),
                    when: new Condition($burstCondition::class),
                ),
                'daily' => new RateLimit(
                    10_000,
                    '1 day',
                    identity: new Identity($dailyIdentity::class),
                    when: new Condition($dailyCondition::class),
                ),
            ],
            identityResolvers: [$burstIdentity, $dailyIdentity],
            conditions: [$burstCondition, $dailyCondition],
        )->resolve(new Get(), 'product_get');

        self::assertSame(
            $burstIdentity->resolve(),
            $resolved[0]->identityResolver?->resolve(),
        );
        self::assertCount(1, $resolved);
    }

    #[Test]
    public function itResolvesDynamicGlobalValuesThroughTheCommonPipeline(): void
    {
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
        $bucketResolver = new class implements BucketResolverInterface {
            public function resolve(): string
            {
                return 'premium';
            }
        };
        $identityResolver = new class implements IdentityResolverInterface {
            public function resolve(): string
            {
                return 'customer';
            }
        };
        $condition = new class implements RateLimitConditionInterface {
            public function matches(): bool
            {
                return true;
            }
        };

        $resolved = $this->resolver(
            globals: ['api' => new RateLimit(
                limit: new DynamicLimit($limitResolver::class),
                interval: '1 minute',
                identity: new Identity($identityResolver::class),
                when: new AllOf([
                    new Condition($condition::class),
                    new Condition($condition::class),
                ]),
                bucket: new DynamicBucket($bucketResolver::class),
                cost: new DynamicCost($costResolver::class),
            )],
            identityResolvers: [$identityResolver],
            conditions: [$condition],
            bucketResolvers: [$bucketResolver],
            limitResolvers: [$limitResolver],
            costResolvers: [$costResolver],
        )->resolve(new Get(), 'product_get');

        self::assertSame('global:api:premium', $resolved[0]->bucket);
        self::assertSame(42, $resolved[0]->definition->limit);
        self::assertSame(3, $resolved[0]->cost);
        self::assertSame('customer', $resolved[0]->identityResolver?->resolve());
    }

    #[Test]
    public function itBypassesADynamicallyNamespacedGlobalByItsFinalName(): void
    {
        $bucketResolver = new class implements BucketResolverInterface {
            public function resolve(): string
            {
                return 'free';
            }
        };

        $resolved = $this->resolver(
            globals: ['api' => new RateLimit(
                limit: 10,
                interval: '1 minute',
                bucket: new DynamicBucket($bucketResolver::class),
            )],
            bucketResolvers: [$bucketResolver],
        )->resolve(new Get(extraProperties: [
            BypassRateLimit::class => new BypassRateLimit(bucket: 'global:api:free'),
        ]), 'product_get');

        self::assertSame([], $resolved);
    }

    #[Test]
    public function itUsesSharedDefinitionsForGlobalsWithoutSkippingResolution(): void
    {
        $definition = new RateLimit(
            limit: 20,
            interval: '1 minute',
            policy: RateLimitPolicy::FIXED_WINDOW,
        );

        $resolved = $this->resolver(
            shared: ['customers' => $definition],
            globals: ['api' => new RateLimit(bucket: 'customers', cost: 2)],
        )->resolve(new Get(), 'product_get');

        self::assertSame('global:api:customers', $resolved[0]->bucket);
        self::assertSame(20, $resolved[0]->definition->limit);
        self::assertSame(2, $resolved[0]->cost);
    }

    #[Test]
    public function itUnconditionallyBypassesAllResolvedRateLimits(): void
    {
        $resolved = $this->resolver(globals: ['burst' => new RateLimit(
            limit: 1_000,
            interval: '1 hour',
            policy: RateLimitPolicy::FIXED_WINDOW,
        )])->resolve(
            operation: new Get(extraProperties: [
                RateLimit::class => new RateLimit(limit: 10, interval: '1 minute'),
                BypassRateLimit::class => new BypassRateLimit(),
            ]),
            operationKey: 'product_get',
        );

        self::assertSame([], $resolved);
    }

    #[Test]
    public function itConditionallyBypassesAllResolvedRateLimits(): void
    {
        $condition = self::createStub(RateLimitConditionInterface::class);
        $condition->method('matches')->willReturn(true);

        $resolved = $this->resolver(conditions: [$condition])->resolve(
            operation: new Get(extraProperties: [
                RateLimit::class => new RateLimit(limit: 10, interval: '1 minute'),
                BypassRateLimit::class => new BypassRateLimit(
                    when: new Condition($condition::class),
                ),
            ]),
            operationKey: 'product_get',
        );

        self::assertSame([], $resolved);
    }

    #[Test]
    public function itKeepsLimitsWhenConditionalBypassDoesNotMatch(): void
    {
        $condition = self::createStub(RateLimitConditionInterface::class);
        $condition->method('matches')->willReturn(false);

        $resolved = $this->resolver(conditions: [$condition])->resolve(
            operation: new Get(extraProperties: [
                RateLimit::class => new RateLimit(limit: 10, interval: '1 minute'),
                BypassRateLimit::class => new BypassRateLimit(
                    when: new Condition($condition::class),
                ),
            ]),
            operationKey: 'product_get',
        );

        self::assertCount(1, $resolved);
    }

    #[Test]
    public function itBypassesOnlyTheMatchingResolvedBucket(): void
    {
        $resolved = $this->resolver()->resolve(
            operation: new Get(extraProperties: [
                RateLimit::class => [
                    new RateLimit(limit: 10, interval: '1 minute', bucket: 'catalog'),
                    new RateLimit(limit: 20, interval: '1 minute', bucket: 'checkout'),
                ],
                BypassRateLimit::class => new BypassRateLimit(bucket: 'catalog'),
            ]),
            operationKey: 'product_get',
        );

        self::assertCount(1, $resolved);
        self::assertSame('shared:checkout', $resolved[0]->bucket);
    }

    #[Test]
    public function itKeepsLimitsForANonMatchingBucket(): void
    {
        $resolved = $this->resolver()->resolve(
            operation: new Get(extraProperties: [
                RateLimit::class => new RateLimit(limit: 10, interval: '1 minute'),
                BypassRateLimit::class => new BypassRateLimit(bucket: 'catalog'),
            ]),
            operationKey: 'product_get',
        );

        self::assertCount(1, $resolved);
        self::assertSame('operation:product_get', $resolved[0]->bucket);
    }

    #[Test]
    public function itBypassesAGeneratedOperationBucketByItsResolvedName(): void
    {
        $resolved = $this->resolver()->resolve(
            operation: new Get(extraProperties: [
                RateLimit::class => new RateLimit(limit: 10, interval: '1 minute'),
                BypassRateLimit::class => new BypassRateLimit(bucket: 'product_get'),
            ]),
            operationKey: 'product_get',
        );

        self::assertSame([], $resolved);
    }

    #[Test]
    public function itBypassesADynamicallyResolvedBucket(): void
    {
        $bucketResolver = new class implements BucketResolverInterface {
            public function resolve(): string
            {
                return 'catalog';
            }
        };

        $resolved = $this->resolver(bucketResolvers: [$bucketResolver])->resolve(
            operation: new Get(extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 10,
                    interval: '1 minute',
                    bucket: new DynamicBucket($bucketResolver::class),
                ),
                BypassRateLimit::class => new BypassRateLimit(bucket: 'catalog'),
            ]),
            operationKey: 'product_get',
        );

        self::assertSame([], $resolved);
    }

    #[Test]
    public function itBypassesOnlyTheNamedGlobalBucketByItsResolvedName(): void
    {
        $resolved = $this->resolver(globals: [
            'burst' => new RateLimit(100, '1 minute'),
            'daily' => new RateLimit(10_000, '1 day'),
        ])->resolve(
            operation: new Get(extraProperties: [
                BypassRateLimit::class => new BypassRateLimit(bucket: 'global:burst'),
            ]),
            operationKey: 'product_get',
        );

        self::assertCount(1, $resolved);
        self::assertSame('global:daily', $resolved[0]->bucket);
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
            InvalidRateLimitException::class,
        );

        $this->expectExceptionMessage(
            'Operation key cannot be empty.',
        );

        $this->resolver()->resolve(
            operation: $operation,
            operationKey: '',
        );
    }

    #[Test]
    public function itRejectsEmptyLegacyIdentityServiceId(): void
    {
        $method = new \ReflectionMethod(RateLimitResolver::class, 'resolveIdentity');

        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage('Identity resolver service ID cannot be empty.');

        $method->invoke($this->resolver(), ' ');
    }

    #[Test]
    public function itSupportsLegacyIdentityServiceId(): void
    {
        $identityResolver = self::createStub(IdentityResolverInterface::class);
        $method = new \ReflectionMethod(RateLimitResolver::class, 'resolveIdentity');

        self::assertInstanceOf(
            IdentityResolverInterface::class,
            $method->invoke($this->resolver(
                identityResolvers: ['app.identity' => $identityResolver],
            ), 'app.identity'),
        );
    }

    #[Test]
    public function itRejectsResolvingADeclarationWithoutALimit(): void
    {
        $method = new \ReflectionMethod(RateLimitResolver::class, 'resolveDefinition');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must have a limit');

        $method->invoke($this->resolver(), new RateLimit(bucket: 'catalog'));
    }

    #[Test]
    public function itRejectsResolvingALimitWithoutAnInterval(): void
    {
        $reflection = new \ReflectionClass(RateLimit::class);
        $rateLimit = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('limit')->setValue($rateLimit, 10);
        $reflection->getProperty('interval')->setValue($rateLimit, null);

        $method = new \ReflectionMethod(RateLimitResolver::class, 'resolveDefinition');

        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(
            'Rate limit interval cannot be omitted when a limit is set.',
        );

        $method->invoke($this->resolver(), $rateLimit);
    }

    /**
     * @param array<string, RateLimit|RateLimitDefinition> $shared
     * @param list<RateLimitProviderInterface> $providers
     * @param array<array-key, IdentityResolverInterface> $identityResolvers
     * @param list<RateLimitConditionInterface> $conditions
     * @param array<string, RateLimit> $globals
     * @param list<BucketResolverInterface> $bucketResolvers
     * @param list<LimitResolverInterface> $limitResolvers
     * @param list<CostResolverInterface> $costResolvers
     */
    private function resolver(
        array $shared = [],
        array $providers = [],
        array $identityResolvers = [],
        array $conditions = [],
        array $globals = [],
        array $bucketResolvers = [],
        array $limitResolvers = [],
        array $costResolvers = [],
    ): RateLimitResolver {
        $strategyRegistry = new RateLimitStrategyRegistry(
            $identityResolvers,
            $conditions,
            $bucketResolvers,
            $limitResolvers,
            $costResolvers,
        );

        return new RateLimitResolver(
            metadataExtractor: new RateLimitMetadataExtractor(),
            providerCollection: new RateLimitProviderCollection(
                $providers,
            ),
            intervalNormalizer: new IntervalNormalizer(),
            sharedRateLimitRegistry: new SharedRateLimitRegistry(array_map(
                static fn (RateLimit|RateLimitDefinition $definition): RateLimit =>
                    $definition instanceof RateLimit
                        ? $definition
                        : new RateLimit(
                            limit: $definition->limit,
                            interval: sprintf('%d seconds', $definition->intervalSeconds),
                            policy: $definition->policy,
                        ),
                $shared,
            )),
            strategyRegistry: $strategyRegistry,
            globalRateLimits: $globals,
            conditionEvaluator: new RateLimitConditionEvaluator($strategyRegistry),
        );
    }
}
