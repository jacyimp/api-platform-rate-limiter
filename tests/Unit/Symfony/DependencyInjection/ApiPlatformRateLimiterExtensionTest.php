<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Symfony\DependencyInjection;

use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitMetadataExtractor;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitProviderCollection;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitResolver;
use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
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
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

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
                CacheStorage::class,
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

        self::assertSame(
            CacheStorage::class,
            (string) $container->getAlias(
                StorageInterface::class,
            ),
        );
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

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();

        $extension = new ApiPlatformRateLimiterExtension();
        $extension->load([], $container);

        return $container;
    }
}
