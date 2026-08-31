<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Laravel;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
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
use JacyImp\ApiPlatformRateLimiter\Core\IdentityExpressionEvaluator;
use JacyImp\ApiPlatformRateLimiter\Core\IntervalNormalizer;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitBypassChecker;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitConditionEvaluator;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitConfigurationFactory;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitEnforcer;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimiterInterface;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitStrategyRegistry;
use JacyImp\ApiPlatformRateLimiter\Core\SharedRateLimitRegistry;
use JacyImp\ApiPlatformRateLimiter\Laravel\Middleware\ApiPlatformRateLimitMiddleware;
use JacyImp\ApiPlatformRateLimiter\Symfony\SymfonyRateLimiter;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

final class LaravelServiceProvider extends ServiceProvider
{
    public const MIDDLEWARE = 'api-platform-rate-limiter';

    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__, 2) . '/config/api-platform-rate-limiter.php',
            'api-platform-rate-limiter',
        );
        $this->registerApiPlatformMiddleware();

        $this->app->singleton(RateLimitConfigurationFactory::class);
        $this->app->singleton(RateLimitMetadataExtractor::class);
        $this->app->singleton(IntervalNormalizer::class);

        $this->app->scoped(
            RateLimitProviderCollection::class,
            fn (Application $app): RateLimitProviderCollection => new RateLimitProviderCollection(
                $this->services($app, 'providers', RateLimitProviderInterface::class),
            ),
        );
        $this->app->scoped(
            RateLimitStrategyRegistry::class,
            fn (Application $app): RateLimitStrategyRegistry => new RateLimitStrategyRegistry(
                $this->services($app, 'resolvers.identity', IdentityResolverInterface::class),
                $this->services($app, 'resolvers.condition', RateLimitConditionInterface::class),
                $this->services($app, 'resolvers.bucket', BucketResolverInterface::class),
                $this->services($app, 'resolvers.limit', LimitResolverInterface::class),
                $this->services($app, 'resolvers.cost', CostResolverInterface::class),
            ),
        );
        $this->app->singleton(SharedRateLimitRegistry::class, function (Application $app): SharedRateLimitRegistry {
            $factory = $app->make(RateLimitConfigurationFactory::class);

            return new SharedRateLimitRegistry($factory->buckets(
                $this->rateLimitConfiguration($app, 'buckets'),
            ));
        });
        $this->app->scoped(
            RateLimitConditionEvaluator::class,
            static fn (Application $app): RateLimitConditionEvaluator => new RateLimitConditionEvaluator(
                $app->make(RateLimitStrategyRegistry::class),
            ),
        );
        $this->app->scoped(
            IdentityExpressionEvaluator::class,
            static fn (Application $app): IdentityExpressionEvaluator => new IdentityExpressionEvaluator(
                $app->make(RateLimitStrategyRegistry::class),
            ),
        );
        $this->app->scoped(RateLimitResolver::class, function (Application $app): RateLimitResolver {
            $factory = $app->make(RateLimitConfigurationFactory::class);

            return new RateLimitResolver(
                $app->make(RateLimitMetadataExtractor::class),
                $app->make(RateLimitProviderCollection::class),
                $app->make(IntervalNormalizer::class),
                $app->make(SharedRateLimitRegistry::class),
                $app->make(RateLimitStrategyRegistry::class),
                $factory->globals($this->rateLimitConfiguration($app, 'globals')),
                $app->make(IdentityExpressionEvaluator::class),
                $app->make(RateLimitConditionEvaluator::class),
            );
        });

        $this->app->singleton(StorageInterface::class, static function (Application $app): StorageInterface {
            $config = $app->make(ConfigRepository::class);
            $service = $config->get('api-platform-rate-limiter.storage.service');
            if (is_string($service) && trim($service) !== '') {
                $storage = $app->make($service);
                if (!$storage instanceof StorageInterface) {
                    throw new \InvalidArgumentException(sprintf(
                        'Configured storage service "%s" must implement %s.',
                        $service,
                        StorageInterface::class,
                    ));
                }

                return $storage;
            }

            $store = $config->get('api-platform-rate-limiter.storage.store');
            $cache = $app->make(CacheManager::class)->store(is_string($store) ? $store : null);

            return new LaravelCacheStorage($cache);
        });
        $this->app->singleton(
            SymfonyRateLimiter::class,
            static fn (Application $app): SymfonyRateLimiter => new SymfonyRateLimiter(
                $app->make(StorageInterface::class),
            ),
        );
        $this->app->alias(SymfonyRateLimiter::class, RateLimiterInterface::class);

        $this->app->scoped(LaravelIdentityResolver::class);
        $this->app->alias(LaravelIdentityResolver::class, IdentityResolverInterface::class);
        $this->app->singleton(LaravelRateLimitRejectionHandler::class);
        $this->app->alias(
            LaravelRateLimitRejectionHandler::class,
            RateLimitRejectionHandlerInterface::class,
        );
        $this->app->scoped(
            RateLimitBypassChecker::class,
            fn (Application $app): RateLimitBypassChecker => new RateLimitBypassChecker(
                $this->services($app, 'bypasses', RateLimitBypassInterface::class),
            ),
        );
        $this->app->alias(RateLimitBypassChecker::class, RateLimitBypassInterface::class);
        $this->app->singleton(LaravelEventDispatcher::class);
        $this->app->alias(LaravelEventDispatcher::class, EventDispatcherInterface::class);
        $this->app->scoped(
            RateLimitEnforcer::class,
            static fn (Application $app): RateLimitEnforcer => new RateLimitEnforcer(
                $app->make(RateLimiterInterface::class),
                $app->make(IdentityResolverInterface::class),
                $app->make(RateLimitBypassInterface::class),
                $app->make(EventDispatcherInterface::class),
            ),
        );
        $this->app->scoped(ApiPlatformRateLimitMiddleware::class);
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware(self::MIDDLEWARE, ApiPlatformRateLimitMiddleware::class);

        $this->publishes([
            dirname(__DIR__, 2) . '/config/api-platform-rate-limiter.php'
                => $this->app->configPath('api-platform-rate-limiter.php'),
        ], 'api-platform-rate-limiter-config');
    }

    private function registerApiPlatformMiddleware(): void
    {
        $config = $this->app->make(ConfigRepository::class);
        $middleware = $config->get('api-platform.defaults.middleware', []);
        $middleware = is_array($middleware) ? $middleware : [$middleware];

        if (!in_array(self::MIDDLEWARE, $middleware, true)) {
            $middleware[] = self::MIDDLEWARE;
        }

        $config->set('api-platform.defaults.middleware', $middleware);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $contract
     *
     * @return array<string, T>
     */
    private function services(Application $app, string $key, string $contract): array
    {
        $configured = $app
            ->make(ConfigRepository::class)
            ->get('api-platform-rate-limiter.' . $key, []);
        if (!is_array($configured)) {
            throw new \InvalidArgumentException(sprintf(
                'Rate limiter configuration "%s" must be an array.',
                $key,
            ));
        }
        $services = [];

        foreach ($configured as $name => $service) {
            if (!is_string($service)) {
                throw new \InvalidArgumentException(sprintf(
                    'Configured %s service must be a container ID.',
                    $key,
                ));
            }

            $resolved = $app->make($service);
            if (!$resolved instanceof $contract) {
                throw new \InvalidArgumentException(sprintf(
                    'Configured service "%s" must implement %s.',
                    $service,
                    $contract,
                ));
            }

            $services[is_string($name) ? $name : $service] = $resolved;
        }

        return $services;
    }

    /** @return array<string, array<string, mixed>> */
    private function rateLimitConfiguration(Application $app, string $key): array
    {
        $value = $app
            ->make(ConfigRepository::class)
            ->get('api-platform-rate-limiter.' . $key, []);

        if (!is_array($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Rate limiter configuration "%s" must be an array.',
                $key,
            ));
        }

        $configuration = [];

        foreach ($value as $name => $item) {
            if (!is_string($name) || !is_array($item)) {
                throw new \InvalidArgumentException(sprintf(
                    'Rate limiter configuration "%s" must contain named arrays.',
                    $key,
                ));
            }

            $values = [];
            foreach ($item as $itemKey => $itemValue) {
                if (!is_string($itemKey)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Rate limiter configuration "%s.%s" must use named options.',
                        $key,
                        $name,
                    ));
                }

                $values[$itemKey] = $itemValue;
            }

            $configuration[$name] = $values;
        }

        return $configuration;
    }
}
