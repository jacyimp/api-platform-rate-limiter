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
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitEnforcer;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimiterInterface;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitStrategyRegistry;
use JacyImp\ApiPlatformRateLimiter\Core\SharedRateLimitRegistry;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AllOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AnyOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Condition;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Not;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicBucket;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicCost;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\CompositeIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\FirstAvailableIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\Identity;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;
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

/**
 * @internal
 */
final class ApiPlatformRateLimiterExtension extends Extension
{
    public const STORAGE_SERVICE = 'jacyimp.api_platform_rate_limiter.storage';
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
         *         limit: int|null,
         *         limit_resolver: string|null,
         *         interval: string|null,
         *         policy: string,
         *         identity_resolver: string|null,
         *         identity: mixed,
         *         when: mixed,
         *         bucket: string|null,
         *         bucket_resolver: string|null,
         *         cost: int,
         *         cost_resolver: string|null
         *     }>,
         *     buckets: array<string, array{
         *         limit: int|null,
         *         limit_resolver: string|null,
         *         interval: string,
         *         policy: string,
         *         identity_resolver: string|null,
         *         identity: mixed,
         *         when: mixed,
         *         cost: int,
         *         cost_resolver: string|null
         *     }>,
         *     storage: string|null,
         *     cache_pool: string
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
                $this->configuredRateLimits(
                    $config['buckets'],
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
                $this->globalRateLimits($config['globals']),
                new Reference(IdentityExpressionEvaluator::class),
                new Reference(RateLimitConditionEvaluator::class),
            ]);

        if ($config['storage'] === null) {
            $container
                ->register(self::STORAGE_SERVICE, CacheStorage::class)
                ->setArguments([
                    new Reference($config['cache_pool']),
                ]);
        } else {
            $container->setAlias(self::STORAGE_SERVICE, $config['storage']);
        }

        $container
            ->register(SymfonyRateLimiter::class)
            ->setArguments([
                new Reference(self::STORAGE_SERVICE),
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
     *     limit: int|null,
     *     limit_resolver: string|null,
     *     interval: string,
     *     policy: string,
     *     identity_resolver: string|null,
     *     identity: mixed,
     *     when: mixed,
     *     cost: int,
     *     cost_resolver: string|null
     * }> $rateLimits
     *
     * @return array<string, Definition>
     */
    private function configuredRateLimits(array $rateLimits): array
    {
        $definitions = [];

        foreach ($rateLimits as $name => $rateLimit) {
            $definitions[$name] = $this->rateLimitDeclaration($rateLimit);
        }

        return $definitions;
    }

    /**
     * @param array<string, array{
     *     limit: int|null,
     *     limit_resolver: string|null,
     *     interval: string|null,
     *     policy: string,
     *     identity_resolver: string|null,
     *     identity: mixed,
     *     when: mixed,
     *     bucket: string|null,
     *     bucket_resolver: string|null,
     *     cost: int,
     *     cost_resolver: string|null
     * }> $rateLimits
     *
     * @return array<string, Definition>
     */
    private function globalRateLimits(array $rateLimits): array
    {
        $definitions = [];

        foreach ($rateLimits as $name => $rateLimit) {
            $limit = $rateLimit['limit_resolver'] === null
                ? $rateLimit['limit']
                : new Definition(DynamicLimit::class, [$rateLimit['limit_resolver']]);
            $bucket = $rateLimit['bucket_resolver'] === null
                ? $rateLimit['bucket']
                : new Definition(DynamicBucket::class, [$rateLimit['bucket_resolver']]);
            $cost = $rateLimit['cost_resolver'] === null
                ? $rateLimit['cost']
                : new Definition(DynamicCost::class, [$rateLimit['cost_resolver']]);

            $definitions[$name] = new Definition(RateLimit::class, [
                $limit,
                $rateLimit['interval'],
                RateLimitPolicy::from($rateLimit['policy']),
                $this->globalIdentity($rateLimit),
                $rateLimit['when'] === null
                    ? null
                    : $this->conditionExpression($rateLimit['when']),
                $bucket,
                $cost,
            ]);
        }

        return $definitions;
    }

    /**
     * @param array{
     *     limit: int|null,
     *     limit_resolver: string|null,
     *     interval: string,
     *     policy: string,
     *     identity_resolver: string|null,
     *     identity: mixed,
     *     when: mixed,
     *     cost: int,
     *     cost_resolver: string|null
     * } $rateLimit
     */
    private function rateLimitDeclaration(array $rateLimit): Definition
    {
        $limit = $rateLimit['limit_resolver'] === null
            ? $rateLimit['limit']
            : new Definition(DynamicLimit::class, [$rateLimit['limit_resolver']]);
        $cost = $rateLimit['cost_resolver'] === null
            ? $rateLimit['cost']
            : new Definition(DynamicCost::class, [$rateLimit['cost_resolver']]);

        return new Definition(RateLimit::class, [
            $limit,
            $rateLimit['interval'],
            RateLimitPolicy::from($rateLimit['policy']),
            $this->globalIdentity($rateLimit),
            $rateLimit['when'] === null
                ? null
                : $this->conditionExpression($rateLimit['when']),
            null,
            $cost,
        ]);
    }

    /**
     * @param array{identity: mixed, identity_resolver: string|null} $rateLimit
     */
    private function globalIdentity(array $rateLimit): ?Definition
    {
        if ($rateLimit['identity'] !== null) {
            return $this->identityExpression($rateLimit['identity']);
        }

        return $rateLimit['identity_resolver'] === null
            ? null
            : new Definition(Identity::class, [$rateLimit['identity_resolver']]);
    }

    private function identityExpression(mixed $value): Definition
    {
        if (is_string($value)) {
            return new Definition(Identity::class, [$value]);
        }

        if (!is_array($value) || count($value) !== 1) {
            throw new \InvalidArgumentException('Invalid global identity expression.');
        }

        $operator = array_key_first($value);
        $children = is_string($operator) ? $value[$operator] : null;
        if (!is_array($children)) {
            throw new \InvalidArgumentException('Global identity expression children must be a list.');
        }

        $expressions = array_map(
            fn (mixed $child): Definition => $this->identityExpression($child),
            array_values($children),
        );

        return match ($operator) {
            'composite' => new Definition(CompositeIdentity::class, [$expressions]),
            'first_available' => new Definition(FirstAvailableIdentity::class, [$expressions]),
            default => throw new \InvalidArgumentException(sprintf(
                'Unknown global identity operator "%s".',
                $operator,
            )),
        };
    }

    private function conditionExpression(mixed $value): Definition
    {
        if (is_string($value)) {
            return new Definition(Condition::class, [$value]);
        }

        if (!is_array($value) || count($value) !== 1) {
            throw new \InvalidArgumentException('Invalid global condition expression.');
        }

        $operator = array_key_first($value);
        $operand = is_string($operator) ? $value[$operator] : null;
        if ($operator === 'not') {
            return new Definition(Not::class, [$this->conditionExpression($operand)]);
        }

        if (!is_array($operand)) {
            throw new \InvalidArgumentException('Global condition expression children must be a list.');
        }

        $conditions = array_map(
            fn (mixed $child): Definition => $this->conditionExpression($child),
            array_values($operand),
        );

        return match ($operator) {
            'all_of' => new Definition(AllOf::class, [$conditions]),
            'any_of' => new Definition(AnyOf::class, [$conditions]),
            default => throw new \InvalidArgumentException(sprintf(
                'Unknown global condition operator "%s".',
                $operator,
            )),
        };
    }
}
