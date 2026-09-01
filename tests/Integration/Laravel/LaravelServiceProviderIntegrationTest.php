<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel;

use Generator;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitMetadataExtractor;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitProviderCollection;
use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejectionHandlerInterface;
use JacyImp\ApiPlatformRateLimiter\Core\IdentityExpressionEvaluator;
use JacyImp\ApiPlatformRateLimiter\Core\IntervalNormalizer;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitConditionEvaluator;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitConfigurationFactory;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitEnforcer;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimiterInterface;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitStrategyRegistry;
use JacyImp\ApiPlatformRateLimiter\Core\SharedRateLimitRegistry;
use JacyImp\ApiPlatformRateLimiter\Event\RateLimitChecking;
use JacyImp\ApiPlatformRateLimiter\Laravel\LaravelCacheStorage;
use JacyImp\ApiPlatformRateLimiter\Laravel\LaravelEventDispatcher;
use JacyImp\ApiPlatformRateLimiter\Laravel\LaravelIdentityResolver;
use JacyImp\ApiPlatformRateLimiter\Laravel\LaravelRateLimitRejectionHandler;
use JacyImp\ApiPlatformRateLimiter\Laravel\LaravelServiceProvider;
use JacyImp\ApiPlatformRateLimiter\Laravel\Middleware\ApiPlatformRateLimitMiddleware;
use JacyImp\ApiPlatformRateLimiter\Symfony\SymfonyRateLimiter;
use JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture\AlwaysBypass;
use JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture\ApiPlatformOperationMiddleware;
use JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture\Applies;
use JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture\DoesNotApply;
use JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture\FixedBucket;
use JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture\FixedCost;
use JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture\FixedLimit;
use JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture\FixedProvider;
use JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture\MissingIdentity;
use JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture\PrimaryIdentity;
use JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture\SecondaryIdentity;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

final class LaravelServiceProviderIntegrationTest extends TestCase
{
    /** @return list<class-string> */
    protected function getPackageProviders(mixed $app): array
    {
        return [LaravelServiceProvider::class];
    }

    protected function defineEnvironment(mixed $app): void
    {
        $config = $app->make(Repository::class);
        $config->set('cache.default', 'array');
        $config->set('cache.stores.rate_limits', ['driver' => 'array']);
        $config->set('api-platform-rate-limiter.storage.store', 'rate_limits');
        $config->set('api-platform-rate-limiter.buckets.configured', [
            'limit' => 1,
            'interval' => '1 minute',
        ]);
        $config->set('api-platform-rate-limiter.resolvers', [
            'identity' => [
                PrimaryIdentity::class,
                SecondaryIdentity::class,
                MissingIdentity::class,
            ],
            'condition' => [Applies::class, DoesNotApply::class],
            'bucket' => [FixedBucket::class],
            'limit' => [FixedLimit::class],
            'cost' => [FixedCost::class],
        ]);
        $config->set('api-platform-rate-limiter.providers', [FixedProvider::class]);
    }

    protected function defineRoutes(mixed $router): void
    {
        $router->get('/limited/{scenario}', static fn (): array => ['ok' => true])
            ->middleware([
                ApiPlatformOperationMiddleware::class,
                ApiPlatformRateLimitMiddleware::class,
            ]);
        $router->get('/ordinary', static fn (): array => ['ok' => true])
            ->middleware(ApiPlatformRateLimitMiddleware::class);
    }

    #[Test]
    public function itBootsAndBindsFrameworkAdapters(): void
    {
        self::assertInstanceOf(
            SymfonyRateLimiter::class,
            $this->application()->make(RateLimiterInterface::class),
        );
        self::assertInstanceOf(
            LaravelIdentityResolver::class,
            $this->application()->make(IdentityResolverInterface::class),
        );
        self::assertInstanceOf(
            LaravelRateLimitRejectionHandler::class,
            $this->application()->make(RateLimitRejectionHandlerInterface::class),
        );
        self::assertSame(
            ApiPlatformRateLimitMiddleware::class,
            $this->application()->make(Router::class)->getMiddleware()[LaravelServiceProvider::MIDDLEWARE],
        );
        self::assertInstanceOf(
            LaravelCacheStorage::class,
            $this->application()->make(StorageInterface::class),
        );
    }

    #[Test]
    public function itRegistersTheExpectedSingletonAndTransientServices(): void
    {
        $application = $this->application();
        $singletons = [
            RateLimitConfigurationFactory::class,
            RateLimitMetadataExtractor::class,
            IntervalNormalizer::class,
            SymfonyRateLimiter::class,
            LaravelRateLimitRejectionHandler::class,
            LaravelEventDispatcher::class,
        ];

        foreach ($singletons as $service) {
            self::assertSame($application->make($service), $application->make($service));
        }

        $transientServices = [
            RateLimitProviderCollection::class,
            RateLimitStrategyRegistry::class,
            RateLimitConditionEvaluator::class,
            IdentityExpressionEvaluator::class,
            LaravelIdentityResolver::class,
            RateLimitEnforcer::class,
            ApiPlatformRateLimitMiddleware::class,
        ];
        foreach ($transientServices as $service) {
            self::assertNotSame($application->make($service), $application->make($service));
        }
    }

    #[Test]
    public function itMergesDefaultsAndPublishesThePackageConfiguration(): void
    {
        $config = $this->application()->make(Repository::class);
        self::assertSame([], $config->get('api-platform-rate-limiter.bypasses'));

        self::assertSame([
            dirname(__DIR__, 3) . '/config/api-platform-rate-limiter.php'
                => $this->application()->configPath('api-platform-rate-limiter.php'),
        ], ServiceProvider::pathsToPublish(
            LaravelServiceProvider::class,
            'api-platform-rate-limiter-config',
        ));
    }

    #[Test]
    public function itUsesAConfiguredStorageService(): void
    {
        $storage = self::createStub(StorageInterface::class);
        $this->application()->instance('custom.storage', $storage);
        $this->application()->make(Repository::class)->set(
            'api-platform-rate-limiter.storage.service',
            'custom.storage',
        );

        self::assertSame(
            $storage,
            $this->application()->make(StorageInterface::class),
        );
    }

    #[Test]
    public function itIgnoresBlankStorageServicesAndNonStringStoreNames(): void
    {
        $config = $this->application()->make(Repository::class);
        $config->set('api-platform-rate-limiter.storage.service', ' ');
        $config->set('api-platform-rate-limiter.storage.store', ['invalid']);
        $this->application()->forgetInstance(StorageInterface::class);

        self::assertInstanceOf(
            LaravelCacheStorage::class,
            $this->application()->make(StorageInterface::class),
        );
    }

    #[Test]
    public function itPreservesScalarApiPlatformMiddlewareConfiguration(): void
    {
        $config = $this->application()->make(Repository::class);
        $config->set('api-platform.defaults.middleware', 'existing.middleware');

        (new LaravelServiceProvider($this->application()))->register();

        self::assertSame([
            'existing.middleware',
            LaravelServiceProvider::MIDDLEWARE,
        ], $config->get('api-platform.defaults.middleware'));
    }

    #[Test]
    public function itPreservesNamedResolverAliases(): void
    {
        $this->application()->make(Repository::class)->set(
            'api-platform-rate-limiter.resolvers.identity',
            ['primary' => PrimaryIdentity::class],
        );
        $this->application()->forgetScopedInstances();

        self::assertInstanceOf(
            PrimaryIdentity::class,
            $this->application()
                ->make(RateLimitStrategyRegistry::class)
                ->identityResolver('primary'),
        );
    }

    #[Test]
    public function itPreservesEveryConfiguredBucket(): void
    {
        $this->application()->make(Repository::class)->set(
            'api-platform-rate-limiter.buckets',
            [
                'first' => ['limit' => 10, 'interval' => '1 minute'],
                'second' => ['limit' => 20, 'interval' => '2 minutes'],
            ],
        );
        $this->application()->forgetInstance(SharedRateLimitRegistry::class);
        $registry = $this->application()->make(SharedRateLimitRegistry::class);

        self::assertSame(10, $registry->get('first')->limit);
        self::assertSame(20, $registry->get('second')->limit);
    }

    #[Test]
    public function itRejectsAConfiguredStorageServiceWithTheWrongType(): void
    {
        $this->application()->instance('invalid.storage', new \stdClass());
        $this->application()->make(Repository::class)->set(
            'api-platform-rate-limiter.storage.service',
            'invalid.storage',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');

        $this->application()->make(StorageInterface::class);
    }

    #[Test]
    public function itOnlyEnforcesRequestsWithAnApiPlatformOperation(): void
    {
        $this->getJson('/ordinary')->assertOk();
        $this->getJson('/ordinary')->assertOk();
    }

    #[Test]
    public function itAllowsThenRejectsAnOperationLimitWithStandardHeaders(): void
    {
        $this->getJson('/limited/operation')->assertOk();
        $this->getJson('/limited/operation')
            ->assertStatus(429)
            ->assertHeader('RateLimit-Limit', '1')
            ->assertHeader('RateLimit-Remaining', '0')
            ->assertHeader('Retry-After');
    }

    /** @return iterable<string, array{string, int}> */
    public static function enforcedFeatures(): iterable
    {
        yield 'configured bucket' => ['configured', 2];
        yield 'dynamic limit' => ['dynamic-limit', 2];
        yield 'dynamic bucket' => ['dynamic-bucket', 2];
        yield 'dynamic cost' => ['dynamic-cost', 2];
        yield 'composite identity' => ['composite', 2];
        yield 'fallback identity' => ['fallback', 2];
        yield 'provider limit' => ['provider', 2];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('enforcedFeatures')]
    public function itSupportsCommonResolutionFeatures(string $scenario, int $rejectedRequest): void
    {
        $response = $this->getJson('/limited/' . $scenario);
        for ($request = 2; $request <= $rejectedRequest; ++$request) {
            $response = $this->getJson('/limited/' . $scenario);
        }

        $response->assertStatus(429);
    }

    #[Test]
    public function itSupportsComposableConditionsAndDeclarativeBypasses(): void
    {
        $this->getJson('/limited/condition')->assertOk();
        $this->getJson('/limited/condition')->assertOk();
        $this->getJson('/limited/declarative-bypass')->assertOk();
        $this->getJson('/limited/declarative-bypass')->assertOk();
    }

    #[Test]
    public function itEnforcesMultipleGlobals(): void
    {
        $config = $this->application()->make(Repository::class);
        $config->set('api-platform-rate-limiter.globals', [
            'burst' => ['limit' => 1, 'interval' => '1 minute'],
            'daily' => ['limit' => 5, 'interval' => '1 day'],
        ]);
        $this->application()->forgetScopedInstances();

        $this->getJson('/limited/plain')->assertOk();
        $this->getJson('/limited/plain')->assertStatus(429);
    }

    #[Test]
    public function itSupportsGlobalBypassesAndLaravelEvents(): void
    {
        $events = 0;
        $this->application()->make(Dispatcher::class)->listen(
            RateLimitChecking::class,
            static function () use (&$events): void {
                ++$events;
            },
        );

        $this->getJson('/limited/events')->assertOk();
        self::assertSame(1, $events);

        $config = $this->application()->make(Repository::class);
        $config->set('api-platform-rate-limiter.bypasses', [AlwaysBypass::class]);
        $this->application()->forgetScopedInstances();

        $this->getJson('/limited/global-bypass')->assertOk();
        $this->getJson('/limited/global-bypass')->assertOk();
    }

    #[Test]
    #[DataProvider('invalidPackageConfigurationProvider')]
    public function itRejectsInvalidPackageConfiguration(
        string $key,
        mixed $value,
        string $service,
        string $message,
        bool $bindInvalidService = false,
    ): void {
        if ($bindInvalidService) {
            $this->application()->instance('invalid.service', new \stdClass());
        }

        $this->application()->make(Repository::class)->set(
            'api-platform-rate-limiter.' . $key,
            $value,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->application()->make($service);
    }

    /**
     * @return Generator<string, array{string, mixed, class-string, string, 4?: bool}>
     */
    public static function invalidPackageConfigurationProvider(): Generator
    {
        yield 'service collection type' => [
            'providers',
            'invalid',
            RateLimitProviderCollection::class,
            'configuration "providers" must be an array',
        ];
        yield 'service ID type' => [
            'providers',
            [123],
            RateLimitProviderCollection::class,
            'Configured providers service must be a container ID',
        ];
        yield 'service contract' => [
            'providers',
            ['invalid.service'],
            RateLimitProviderCollection::class,
            'must implement',
            true,
        ];
        yield 'bucket collection type' => [
            'buckets',
            'invalid',
            SharedRateLimitRegistry::class,
            'configuration "buckets" must be an array',
        ];
        yield 'unnamed bucket' => [
            'buckets',
            [['limit' => 1, 'interval' => '1 minute']],
            SharedRateLimitRegistry::class,
            'must contain named arrays',
        ];
        yield 'bucket value type' => [
            'buckets',
            ['api' => 'invalid'],
            SharedRateLimitRegistry::class,
            'must contain named arrays',
        ];
        yield 'unnamed bucket option' => [
            'buckets',
            ['api' => [1]],
            SharedRateLimitRegistry::class,
            'must use named options',
        ];
    }

    private function application(): Application
    {
        return $this->app
            ?? throw new \LogicException('Laravel application is not initialized.');
    }
}
