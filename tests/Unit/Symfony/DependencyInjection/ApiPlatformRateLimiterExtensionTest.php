<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Tests\Unit\Symfony\DependencyInjection;

use Jacyimp\ApiPlatformRateLimiter\ApiPlatform\RateLimitMetadataExtractor;
use Jacyimp\ApiPlatformRateLimiter\ApiPlatform\RateLimitResolver;
use Jacyimp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use Jacyimp\ApiPlatformRateLimiter\Contract\RateLimitBypassInterface;
use Jacyimp\ApiPlatformRateLimiter\Contract\RateLimiterInterface;
use Jacyimp\ApiPlatformRateLimiter\Core\IntervalNormalizer;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitBypassChecker;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitEnforcer;
use Jacyimp\ApiPlatformRateLimiter\Core\SharedRateLimitRegistry;
use Jacyimp\ApiPlatformRateLimiter\Symfony\DependencyInjection\ApiPlatformRateLimiterExtension;
use Jacyimp\ApiPlatformRateLimiter\Symfony\EventListener\ApiPlatformRateLimitListener;
use Jacyimp\ApiPlatformRateLimiter\Symfony\SymfonyIdentityResolver;
use Jacyimp\ApiPlatformRateLimiter\Symfony\SymfonyRateLimiter;
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
                     IntervalNormalizer::class,
                     SharedRateLimitRegistry::class,
                     RateLimitResolver::class,
                     CacheStorage::class,
                     SymfonyRateLimiter::class,
                     SymfonyIdentityResolver::class,
                     RateLimitBypassChecker::class,
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
            CacheStorage::class,
            (string) $container->getAlias(
                StorageInterface::class,
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
