<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Symfony\DependencyInjection;

use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitMetadataExtractor;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitProviderCollection;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitResolver;
use JacyImp\ApiPlatformRateLimiter\Contract\BucketResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\DynamicCostResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\LimitResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitBypassInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitProviderInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejectionHandlerInterface;
use JacyImp\ApiPlatformRateLimiter\Core\IdentityExpressionEvaluator;
use JacyImp\ApiPlatformRateLimiter\Core\IntervalNormalizer;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitBypassChecker;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitConditionEvaluator;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitDefinition;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitEnforcer;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimiterInterface;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitStrategyRegistry;
use JacyImp\ApiPlatformRateLimiter\Core\SharedRateLimitRegistry;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Condition;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use JacyImp\ApiPlatformRateLimiter\Symfony\EventListener\ApiPlatformRateLimitListener;
use JacyImp\ApiPlatformRateLimiter\Symfony\SymfonyIdentityResolver;
use JacyImp\ApiPlatformRateLimiter\Symfony\SymfonyRateLimiter;
use JacyImp\ApiPlatformRateLimiter\Symfony\SymfonyRateLimitRejectionHandler;
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
    public const BUCKET_RESOLVER_TAG = 'jacyimp.api_platform_rate_limiter.bucket_resolver';
    public const CONDITION_TAG = 'jacyimp.api_platform_rate_limiter.condition';

    public const COST_RESOLVER_TAG = 'jacyimp.api_platform_rate_limiter.cost_resolver';

    public const IDENTITY_RESOLVER_TAG = 'jacyimp.api_platform_rate_limiter.identity_resolver';
    public const LIMIT_RESOLVER_TAG = 'jacyimp.api_platform_rate_limiter.limit_resolver';
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
         *     globals: array<string, array{
         *         limit: int,
         *         interval: string,
         *         policy: string,
         *         identity_resolver: string|null,
         *         when: string|null
         *     }>,
         *     shared_buckets: array<string, array{
         *         limit: int,
         *         interval: string,
         *         policy: string,
         *         identity_resolver: string|null,
         *         when: string|null
         *     }>
         * } $config
         */
        $config = $this->processConfiguration(
            new Configuration(),
            $configs,
        );

        $container
            ->registerForAutoconfiguration(RateLimitConditionInterface::class)
            ->addTag(self::CONDITION_TAG);
        $container
            ->registerForAutoconfiguration(BucketResolverInterface::class)
            ->addTag(self::BUCKET_RESOLVER_TAG);
        $container
            ->registerForAutoconfiguration(DynamicCostResolverInterface::class)
            ->addTag(self::COST_RESOLVER_TAG);
        $container
            ->registerForAutoconfiguration(RateLimitBypassInterface::class)
            ->addTag(self::BYPASS_TAG);

        $container
            ->registerForAutoconfiguration(IdentityResolverInterface::class)
            ->addTag(self::IDENTITY_RESOLVER_TAG);
        $container
            ->registerForAutoconfiguration(LimitResolverInterface::class)
            ->addTag(self::LIMIT_RESOLVER_TAG);
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
            ->register(RateLimitStrategyRegistry::class)
            ->setArguments([
                new TaggedIteratorArgument(
                    self::IDENTITY_RESOLVER_TAG,
                    null,
                    null,
                    true,
                ),
                new TaggedIteratorArgument(
                    self::CONDITION_TAG,
                    null,
                    null,
                    true,
                ),
                new TaggedIteratorArgument(self::BUCKET_RESOLVER_TAG, null, null, true,),
                new TaggedIteratorArgument(self::LIMIT_RESOLVER_TAG, null, null, true,),
                new TaggedIteratorArgument(self::COST_RESOLVER_TAG, null, null, true,),
            ]);
        $container
            ->register(SharedRateLimitRegistry::class)
            ->setArguments([
                $this->rateLimitDefinitions(
                    $config['shared_buckets'],
                ),
            ]);

        $container
            ->register(RateLimitConditionEvaluator::class)
            ->setArguments([
                new Reference(RateLimitStrategyRegistry::class),
            ]);

        $container
            ->register(IdentityExpressionEvaluator::class)
            ->setArguments([
                new Reference(RateLimitStrategyRegistry::class),
            ]);

        $container
            ->register(RateLimitResolver::class)
            ->setArguments([
                new Reference(RateLimitMetadataExtractor::class),
                new Reference(RateLimitProviderCollection::class),
                new Reference(IntervalNormalizer::class),
                new Reference(SharedRateLimitRegistry::class),
                new Reference(RateLimitStrategyRegistry::class),
                $this->rateLimitDefinitions($config['globals']),
                new Reference(IdentityExpressionEvaluator::class),
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
                new Reference('event_dispatcher'),
                new Reference(RateLimitConditionEvaluator::class),
            ]);

        $container->register(SymfonyRateLimitRejectionHandler::class);

        $container->setAlias(
            RateLimitRejectionHandlerInterface::class,
            SymfonyRateLimitRejectionHandler::class,
        );

        $container
            ->register(ApiPlatformRateLimitListener::class)
            ->setArguments([
                new Reference(RateLimitResolver::class),
                new Reference(RateLimitEnforcer::class),
                new Reference(RateLimitRejectionHandlerInterface::class),
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
     *     policy: string,
     *     identity_resolver: string|null,
     *     when: string|null
     * }> $rateLimits
     *
     * @return array<string, Definition>
     */
    private function rateLimitDefinitions(array $rateLimits): array
    {
        $definitions = [];

        foreach ($rateLimits as $name => $rateLimit) {
            $definitions[$name] = $this->rateLimitDefinition($rateLimit);
        }

        return $definitions;
    }

    /**
     * @param array{
     *     limit: int,
     *     interval: string,
     *     policy: string,
     *     identity_resolver: string|null,
     *     when: string|null
     * } $rateLimit
     */
    private function rateLimitDefinition(array $rateLimit): Definition
    {
        $intervalNormalizer = new IntervalNormalizer();

        return new Definition(
            RateLimitDefinition::class,
            [
                $rateLimit['limit'],
                $intervalNormalizer->normalize(
                    $rateLimit['interval'],
                ),
                RateLimitPolicy::from(
                    $rateLimit['policy'],
                ),
                $rateLimit['identity_resolver'],
                $rateLimit['when'] === null
                    ? null
                    : new Definition(Condition::class, [$rateLimit['when']]),
            ],
        );
    }
}
