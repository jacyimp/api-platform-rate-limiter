<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Symfony\DependencyInjection;

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
use JacyImp\ApiPlatformRateLimiter\Symfony\DependencyInjection\ApiPlatformRateLimiterExtension;
use JacyImp\ApiPlatformRateLimiter\Symfony\EventListener\ApiPlatformRateLimitListener;
use JacyImp\ApiPlatformRateLimiter\Symfony\SymfonyIdentityResolver;
use JacyImp\ApiPlatformRateLimiter\Symfony\SymfonyRateLimiter;
use JacyImp\ApiPlatformRateLimiter\Symfony\SymfonyRateLimitRejectionHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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
