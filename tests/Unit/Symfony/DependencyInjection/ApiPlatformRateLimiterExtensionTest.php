<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Symfony\DependencyInjection;

use Generator;
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
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejectionHandlerInterface;
use JacyImp\ApiPlatformRateLimiter\Core\IntervalNormalizer;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitBypassChecker;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitEnforcer;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimiterInterface;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitStrategyRegistry;
use JacyImp\ApiPlatformRateLimiter\Core\SharedRateLimitRegistry;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AllOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AnyOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Condition;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Not;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicBucket;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\CompositeIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\FirstAvailableIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\Identity;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;
use JacyImp\ApiPlatformRateLimiter\Symfony\DependencyInjection\ApiPlatformRateLimiterExtension;
use JacyImp\ApiPlatformRateLimiter\Symfony\EventListener\ApiPlatformRateLimitListener;
use JacyImp\ApiPlatformRateLimiter\Symfony\SymfonyIdentityResolver;
use JacyImp\ApiPlatformRateLimiter\Symfony\SymfonyRateLimiter;
use JacyImp\ApiPlatformRateLimiter\Symfony\SymfonyRateLimitRejectionHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

#[CoversClass(ApiPlatformRateLimiterExtension::class)]
final class ApiPlatformRateLimiterExtensionTest extends TestCase
{
    #[Test]
    public function itRegistersRateLimiterServices(): void
    {
        $container = $this->container();

        foreach (
            [
                RateLimitMetadataExtractor::class,
                RateLimitProviderCollection::class,
                IntervalNormalizer::class,
                SharedRateLimitRegistry::class,
                RateLimitResolver::class,
                ApiPlatformRateLimiterExtension::STORAGE_SERVICE,
                SymfonyRateLimiter::class,
                SymfonyRateLimitRejectionHandler::class,
                SymfonyIdentityResolver::class,
                RateLimitBypassChecker::class,
                RateLimitStrategyRegistry::class,
                RateLimitEnforcer::class,
                ApiPlatformRateLimitListener::class,
            ] as $service
        ) {
            self::assertTrue(
                $container->hasDefinition($service),
                sprintf(
                    'Expected service "%s" to be registered.',
                    $service,
                ),
            );
        }
    }

    #[Test]
    public function itRegistersContractAliases(): void
    {
        $container = $this->container();

        self::assertSame(
            SymfonyRateLimiter::class,
            (string) $container->getAlias(
                RateLimiterInterface::class,
            ),
        );

        self::assertSame(
            SymfonyIdentityResolver::class,
            (string) $container->getAlias(
                IdentityResolverInterface::class,
            ),
        );

        self::assertSame(
            RateLimitBypassChecker::class,
            (string) $container->getAlias(
                RateLimitBypassInterface::class,
            ),
        );

        self::assertSame(
            SymfonyRateLimitRejectionHandler::class,
            (string) $container->getAlias(
                RateLimitRejectionHandlerInterface::class,
            ),
        );

        self::assertFalse($container->hasAlias(
            \Symfony\Component\RateLimiter\Storage\StorageInterface::class,
        ));
        self::assertSame(
            CacheStorage::class,
            $container->getDefinition(
                ApiPlatformRateLimiterExtension::STORAGE_SERVICE,
            )->getClass(),
        );
    }

    #[Test]
    public function itUsesTheConfiguredCachePoolForPackageStorage(): void
    {
        $container = new ContainerBuilder();
        (new ApiPlatformRateLimiterExtension())->load([[
            'cache_pool' => 'cache.rate_limiter',
        ]], $container);

        $argument = $container
            ->getDefinition(ApiPlatformRateLimiterExtension::STORAGE_SERVICE)
            ->getArgument(0);

        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame('cache.rate_limiter', (string) $argument);
    }

    #[Test]
    public function itUsesAConfiguredStorageServiceWithoutAFrameworkAlias(): void
    {
        $container = new ContainerBuilder();
        (new ApiPlatformRateLimiterExtension())->load([[
            'storage' => 'app.rate_limit_storage',
        ]], $container);

        self::assertSame(
            'app.rate_limit_storage',
            (string) $container->getAlias(
                ApiPlatformRateLimiterExtension::STORAGE_SERVICE,
            ),
        );
        self::assertFalse($container->hasAlias(
            \Symfony\Component\RateLimiter\Storage\StorageInterface::class,
        ));
    }

    #[Test]
    public function itAcceptsDynamicAndComposableConfiguredBuckets(): void
    {
        $container = new ContainerBuilder();

        (new ApiPlatformRateLimiterExtension())->load([[
            'buckets' => [
                'api' => [
                    'limit' => ['resolver' => 'app.limit_resolver'],
                    'interval' => '1 minute',
                    'cost' => ['resolver' => 'app.cost_resolver'],
                    'identity' => [
                        'first_available' => ['app.api_key', 'app.user'],
                    ],
                    'when' => [
                        'all_of' => [
                            'app.authenticated',
                            ['not' => 'app.internal'],
                        ],
                    ],
                    'policy' => 'fixed_window',
                ],
            ],
        ]], $container);

        $definitions = $container
            ->getDefinition(SharedRateLimitRegistry::class)
            ->getArgument(0);

        self::assertIsArray($definitions);
        self::assertArrayHasKey('api', $definitions);
    }

    #[Test]
    public function itAcceptsDynamicGlobalBucketAndAllExpressionOperators(): void
    {
        $container = new ContainerBuilder();

        (new ApiPlatformRateLimiterExtension())->load([[
            'globals' => [
                'api' => [
                    'bucket' => ['resolver' => 'app.bucket_resolver'],
                    'identity' => [
                        'composite' => [
                            'preferred' => [
                                'first_available' => [
                                    'user' => 'app.user',
                                    'ip' => 'app.ip',
                                ],
                            ],
                        ],
                    ],
                    'when' => [
                        'any_of' => [
                            'enabled' => ['all_of' => ['primary' => 'app.enabled']],
                            'allowed' => ['not' => 'app.blocked'],
                        ],
                    ],
                ],
            ],
        ]], $container);

        $globals = $container->getDefinition(RateLimitResolver::class)->getArgument(5);

        self::assertIsArray($globals);
        self::assertArrayHasKey('api', $globals);

        $global = $globals['api'];
        self::assertInstanceOf(Definition::class, $global);
        self::assertSame(RateLimit::class, $global->getClass());

        $bucket = $global->getArgument(2);
        self::assertInstanceOf(Definition::class, $bucket);
        self::assertSame(DynamicBucket::class, $bucket->getClass());
        self::assertSame('app.bucket_resolver', $bucket->getArgument(0));

        $identity = $global->getArgument(4);
        self::assertInstanceOf(Definition::class, $identity);
        self::assertSame(CompositeIdentity::class, $identity->getClass());
        $compositeChildren = $identity->getArgument(0);
        self::assertIsArray($compositeChildren);
        self::assertSame([0], array_keys($compositeChildren));
        $fallback = $compositeChildren[0];
        self::assertInstanceOf(Definition::class, $fallback);
        self::assertSame(FirstAvailableIdentity::class, $fallback->getClass());
        $fallbackChildren = $fallback->getArgument(0);
        self::assertIsArray($fallbackChildren);
        self::assertSame([0, 1], array_keys($fallbackChildren));
        self::assertInstanceOf(Definition::class, $fallbackChildren[0]);
        self::assertSame(Identity::class, $fallbackChildren[0]->getClass());
        self::assertSame('app.user', $fallbackChildren[0]->getArgument(0));
        self::assertInstanceOf(Definition::class, $fallbackChildren[1]);
        self::assertSame(Identity::class, $fallbackChildren[1]->getClass());
        self::assertSame('app.ip', $fallbackChildren[1]->getArgument(0));

        $condition = $global->getArgument(5);
        self::assertInstanceOf(Definition::class, $condition);
        self::assertSame(AnyOf::class, $condition->getClass());
        $anyChildren = $condition->getArgument(0);
        self::assertIsArray($anyChildren);
        self::assertSame([0, 1], array_keys($anyChildren));
        self::assertInstanceOf(Definition::class, $anyChildren[0]);
        self::assertSame(AllOf::class, $anyChildren[0]->getClass());
        $allChildren = $anyChildren[0]->getArgument(0);
        self::assertIsArray($allChildren);
        self::assertInstanceOf(Definition::class, $allChildren[0]);
        self::assertSame(Condition::class, $allChildren[0]->getClass());
        self::assertSame('app.enabled', $allChildren[0]->getArgument(0));
        self::assertInstanceOf(Definition::class, $anyChildren[1]);
        self::assertSame(Not::class, $anyChildren[1]->getClass());
        $negated = $anyChildren[1]->getArgument(0);
        self::assertInstanceOf(Definition::class, $negated);
        self::assertSame(Condition::class, $negated->getClass());
        self::assertSame('app.blocked', $negated->getArgument(0));
    }

    /** @param array<string, mixed> $expression */
    #[Test]
    #[DataProvider('invalidExpressionProvider')]
    public function itRejectsInvalidGlobalExpressions(
        string $option,
        array $expression,
        string $message,
    ): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new ApiPlatformRateLimiterExtension())->load([[
            'globals' => [
                'invalid' => [
                    'limit' => 1,
                    'interval' => '1 minute',
                    $option => $expression,
                ],
            ],
        ]], new ContainerBuilder());
    }

    /** @return Generator<string, array{string, array<string, mixed>, string}> */
    public static function invalidExpressionProvider(): Generator
    {
        yield 'identity shape' => ['identity', [], 'Invalid global identity expression.'];
        yield 'identity children' => [
            'identity',
            ['composite' => 'app.user'],
            'Global identity expression children must be a list.',
        ];
        yield 'identity operator' => [
            'identity',
            ['unknown' => ['app.user']],
            'Unknown global identity operator "unknown".',
        ];
        yield 'condition shape' => ['when', [], 'Invalid global condition expression.'];
        yield 'condition children' => [
            'when',
            ['all_of' => 'app.enabled'],
            'Global condition expression children must be a list.',
        ];
        yield 'condition operator' => [
            'when',
            ['unknown' => ['app.enabled']],
            'Unknown global condition operator "unknown".',
        ];
    }

    #[Test]
    public function itAutoconfiguresGlobalBypasses(): void
    {
        $container = $this->container();

        $childDefinition = $container
            ->getAutoconfiguredInstanceof()
        [RateLimitBypassInterface::class];

        self::assertTrue(
            $childDefinition->hasTag(
                ApiPlatformRateLimiterExtension::BYPASS_TAG,
            ),
        );
    }

    #[Test]
    public function itAutoconfiguresRateLimitConditions(): void
    {
        $container = $this->container();

        $childDefinition = $container
            ->getAutoconfiguredInstanceof()
        [RateLimitConditionInterface::class];

        self::assertTrue(
            $childDefinition->hasTag(
                ApiPlatformRateLimiterExtension::CONDITION_TAG,
            ),
        );
    }

    #[Test]
    public function itAutoconfiguresIdentityResolvers(): void
    {
        $container = $this->container();

        $childDefinition = $container
            ->getAutoconfiguredInstanceof()
        [IdentityResolverInterface::class];

        self::assertTrue(
            $childDefinition->hasTag(
                ApiPlatformRateLimiterExtension::IDENTITY_RESOLVER_TAG,
            ),
        );
    }

    #[Test]
    public function itAutoconfiguresDynamicValueResolvers(): void
    {
        $container = $this->container();
        $autoconfiguration = $container->getAutoconfiguredInstanceof();

        self::assertTrue(
            $autoconfiguration[BucketResolverInterface::class]->hasTag(
                ApiPlatformRateLimiterExtension::BUCKET_RESOLVER_TAG,
            ),
        );
        self::assertTrue(
            $autoconfiguration[LimitResolverInterface::class]->hasTag(
                ApiPlatformRateLimiterExtension::LIMIT_RESOLVER_TAG,
            ),
        );
        self::assertTrue(
            $autoconfiguration[CostResolverInterface::class]->hasTag(
                ApiPlatformRateLimiterExtension::COST_RESOLVER_TAG,
            ),
        );
    }

    #[Test]
    public function itAutoconfiguresRateLimitProviders(): void
    {
        $container = $this->container();

        $childDefinition = $container
            ->getAutoconfiguredInstanceof()
        [RateLimitProviderInterface::class];

        self::assertTrue(
            $childDefinition->hasTag(
                ApiPlatformRateLimiterExtension::PROVIDER_TAG,
            ),
        );
    }

    #[Test]
    public function itCollectsTaggedBypasses(): void
    {
        $container = $this->container();

        $argument = $container
            ->getDefinition(
                RateLimitBypassChecker::class,
            )
            ->getArgument(0);

        self::assertInstanceOf(
            TaggedIteratorArgument::class,
            $argument,
        );

        self::assertSame(
            ApiPlatformRateLimiterExtension::BYPASS_TAG,
            $argument->getTag(),
        );
    }

    #[Test]
    public function itCollectsTaggedRateLimitProviders(): void
    {
        $container = $this->container();

        $argument = $container
            ->getDefinition(
                RateLimitProviderCollection::class,
            )
            ->getArgument(0);

        self::assertInstanceOf(
            TaggedIteratorArgument::class,
            $argument,
        );

        self::assertSame(
            ApiPlatformRateLimiterExtension::PROVIDER_TAG,
            $argument->getTag(),
        );
    }

    #[Test]
    public function itIndexesEveryTaggedRuntimeResolverCollection(): void
    {
        $arguments = $this->container()
            ->getDefinition(RateLimitStrategyRegistry::class)
            ->getArguments();

        self::assertCount(5, $arguments);
        foreach ($arguments as $argument) {
            self::assertInstanceOf(TaggedIteratorArgument::class, $argument);
            self::assertTrue($argument->needsIndexes());
        }
    }

    #[Test]
    public function itRegistersRequestListenerBeforeApiPlatformRead(): void
    {
        $container = $this->container();

        $tags = $container
            ->getDefinition(
                ApiPlatformRateLimitListener::class,
            )
            ->getTag('kernel.event_listener');

        self::assertSame(
            [
                [
                    'event' => 'kernel.request',
                    'method' => 'onKernelRequest',
                    'priority' => 6,
                ],
            ],
            $tags,
        );
    }

    #[Test]
    public function itRejectsTheRemovedSingularGlobalConfiguration(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new ApiPlatformRateLimiterExtension())->load([
            'global' => [
                'limit' => 100,
                'interval' => '1 minute',
            ],
        ], new ContainerBuilder());
    }

    #[Test]
    public function itRejectsAnInvalidNamedGlobalConfiguration(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new ApiPlatformRateLimiterExtension())->load([
            'globals' => [
                'burst' => [
                    'limit' => 0,
                    'interval' => '1 minute',
                ],
            ],
        ], new ContainerBuilder());
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();

        $extension = new ApiPlatformRateLimiterExtension();
        $extension->load([], $container);

        return $container;
    }
}
