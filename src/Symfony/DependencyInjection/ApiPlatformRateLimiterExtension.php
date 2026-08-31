<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Symfony\DependencyInjection;

use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitMetadataExtractor;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitProviderCollection;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitResolver;
use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitBypassInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitProviderInterface;
use JacyImp\ApiPlatformRateLimiter\Core\IntervalNormalizer;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitBypassChecker;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitDefinition;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitEnforcer;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimiterInterface;
use JacyImp\ApiPlatformRateLimiter\Core\SharedRateLimitRegistry;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use JacyImp\ApiPlatformRateLimiter\Symfony\EventListener\ApiPlatformRateLimitListener;
use JacyImp\ApiPlatformRateLimiter\Symfony\SymfonyIdentityResolver;
use JacyImp\ApiPlatformRateLimiter\Symfony\SymfonyRateLimiter;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * @internal
 */
final class ApiPlatformRateLimiterExtension extends Extension
{
    public const BYPASS_TAG = 'jacyimp.api_platform_rate_limiter.bypass';

    public const PROVIDER_TAG = 'jacyimp.api_platform_rate_limiter.provider';

    /**
     * @param array<array-key, mixed> $configs
     */
    public function load(
        array $configs,
        ContainerBuilder $container,
    ): void {
        /**
         * @var array{
         *     shared_buckets: array<string, array{
         *         limit: int,
         *         interval: string,
         *         policy: string
         *     }>
         * } $config
         */
        $config = $this->processConfiguration(
            new Configuration(),
            $configs,
        );

        $container
            ->registerForAutoconfiguration(RateLimitBypassInterface::class)
            ->addTag(self::BYPASS_TAG);

        $container
            ->registerForAutoconfiguration(RateLimitProviderInterface::class)
            ->addTag(self::PROVIDER_TAG);

        $container->register(RateLimitMetadataExtractor::class);

        $container
            ->register(RateLimitProviderCollection::class)
            ->setArguments([
                new TaggedIteratorArgument(self::PROVIDER_TAG),
            ]);

        $container->register(IntervalNormalizer::class);

        $container
            ->register(SharedRateLimitRegistry::class)
            ->setArguments([
                $this->sharedRateLimitDefinitions(
                    $config['shared_buckets'],
                ),
            ]);

        $container
            ->register(RateLimitResolver::class)
            ->setArguments([
                new Reference(RateLimitMetadataExtractor::class),
                new Reference(RateLimitProviderCollection::class),
                new Reference(IntervalNormalizer::class),
                new Reference(SharedRateLimitRegistry::class),
            ]);

        $container
            ->register(CacheStorage::class)
            ->setArguments([
                new Reference('cache.app'),
            ]);

        $container->setAlias(
            StorageInterface::class,
            CacheStorage::class,
        );

        $container
            ->register(SymfonyRateLimiter::class)
            ->setArguments([
                new Reference(StorageInterface::class),
            ]);

        $container->setAlias(
            RateLimiterInterface::class,
            SymfonyRateLimiter::class,
        );

        $container
            ->register(SymfonyIdentityResolver::class)
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
            ->register(RateLimitBypassChecker::class)
            ->setArguments([
                new TaggedIteratorArgument(self::BYPASS_TAG),
            ]);

        $container->setAlias(
            RateLimitBypassInterface::class,
            RateLimitBypassChecker::class,
        );

        $container
            ->register(RateLimitEnforcer::class)
            ->setArguments([
                new Reference(RateLimiterInterface::class),
                new Reference(IdentityResolverInterface::class),
                new Reference(RateLimitBypassInterface::class),
            ]);

        $container
            ->register(ApiPlatformRateLimitListener::class)
            ->setArguments([
                new Reference(RateLimitResolver::class),
                new Reference(RateLimitEnforcer::class),
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

    /**
     * @param array<string, array{
     *     limit: int,
     *     interval: string,
     *     policy: string
     * }> $buckets
     *
     * @return array<string, Definition>
     */
    private function sharedRateLimitDefinitions(
        array $buckets,
    ): array {
        $intervalNormalizer = new IntervalNormalizer();

        $definitions = [];

        foreach ($buckets as $name => $bucket) {
            $definitions[$name] = new Definition(
                RateLimitDefinition::class,
                [
                    $bucket['limit'],
                    $intervalNormalizer->normalize(
                        $bucket['interval'],
                    ),
                    RateLimitPolicy::from(
                        $bucket['policy'],
                    ),
                ],
            );
        }

        return $definitions;
    }
}
