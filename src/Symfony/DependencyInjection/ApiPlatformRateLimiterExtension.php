<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Symfony\DependencyInjection;

use Jacyimp\ApiPlatformRateLimiter\ApiPlatform\RateLimitMetadataExtractor;
use Jacyimp\ApiPlatformRateLimiter\ApiPlatform\RateLimitResolver;
use Jacyimp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use Jacyimp\ApiPlatformRateLimiter\Contract\RateLimitBypassInterface;
use Jacyimp\ApiPlatformRateLimiter\Contract\RateLimiterInterface;
use Jacyimp\ApiPlatformRateLimiter\Core\IntervalNormalizer;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitBypassChecker;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitEnforcer;
use Jacyimp\ApiPlatformRateLimiter\Core\SharedRateLimitRegistry;
use Jacyimp\ApiPlatformRateLimiter\Symfony\EventListener\ApiPlatformRateLimitListener;
use Jacyimp\ApiPlatformRateLimiter\Symfony\SymfonyIdentityResolver;
use Jacyimp\ApiPlatformRateLimiter\Symfony\SymfonyRateLimiter;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

final class ApiPlatformRateLimiterExtension extends Extension
{
    public const BYPASS_TAG = 'jacyimp.api_platform_rate_limiter.bypass';

    /**
     * @param array<array-key, mixed> $configs
     */
    public function load(
        array $configs,
        ContainerBuilder $container,
    ): void {
        $container
            ->registerForAutoconfiguration(
                RateLimitBypassInterface::class,
            )
            ->addTag(self::BYPASS_TAG);

        $container->register(
            RateLimitMetadataExtractor::class,
        );

        $container->register(
            IntervalNormalizer::class,
        );

        $container
            ->register(
                SharedRateLimitRegistry::class,
            )
            ->setArguments([
                [],
            ]);

        $container
            ->register(
                RateLimitResolver::class,
            )
            ->setArguments([
                new Reference(
                    RateLimitMetadataExtractor::class,
                ),
                new Reference(
                    IntervalNormalizer::class,
                ),
                new Reference(
                    SharedRateLimitRegistry::class,
                ),
            ]);

        $container
            ->register(
                CacheStorage::class,
            )
            ->setArguments([
                new Reference('cache.app'),
            ]);

        $container->setAlias(
            StorageInterface::class,
            CacheStorage::class,
        );

        $container
            ->register(
                SymfonyRateLimiter::class,
            )
            ->setArguments([
                new Reference(
                    StorageInterface::class,
                ),
            ]);

        $container->setAlias(
            RateLimiterInterface::class,
            SymfonyRateLimiter::class,
        );

        $container
            ->register(
                SymfonyIdentityResolver::class,
            )
            ->setArguments([
                new Reference('request_stack'),
                new Reference(
                    'security.token_storage',
                    ContainerInterface::NULL_ON_INVALID_REFERENCE,
                ),
            ]);

        $container->setAlias(
            IdentityResolverInterface::class,
            SymfonyIdentityResolver::class,
        );

        $container
            ->register(
                RateLimitBypassChecker::class,
            )
            ->setArguments([
                new TaggedIteratorArgument(
                    self::BYPASS_TAG,
                ),
            ]);

        $container->setAlias(
            RateLimitBypassInterface::class,
            RateLimitBypassChecker::class,
        );

        $container
            ->register(
                RateLimitEnforcer::class,
            )
            ->setArguments([
                new Reference(
                    RateLimiterInterface::class,
                ),
                new Reference(
                    IdentityResolverInterface::class,
                ),
                new Reference(
                    RateLimitBypassInterface::class,
                ),
            ]);

        $container
            ->register(
                ApiPlatformRateLimitListener::class,
            )
            ->setArguments([
                new Reference(
                    RateLimitResolver::class,
                ),
                new Reference(
                    RateLimitEnforcer::class,
                ),
            ])
            ->addTag(
                'kernel.event_listener',
                [
                    'event' => 'kernel.request',
                    'method' => 'onKernelRequest',
                    'priority' => 6,
                ],
            );
    }
}
