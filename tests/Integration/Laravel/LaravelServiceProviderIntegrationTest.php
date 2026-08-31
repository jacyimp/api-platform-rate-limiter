<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejectionHandlerInterface;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimiterInterface;
use JacyImp\ApiPlatformRateLimiter\Event\RateLimitChecking;
use JacyImp\ApiPlatformRateLimiter\Laravel\LaravelCacheStorage;
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

    private function application(): Application
    {
        return $this->app
            ?? throw new \LogicException('Laravel application is not initialized.');
    }
}
