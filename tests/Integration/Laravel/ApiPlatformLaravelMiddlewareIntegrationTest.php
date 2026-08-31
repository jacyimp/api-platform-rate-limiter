<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation\Factory\OperationMetadataFactory;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use JacyImp\ApiPlatformRateLimiter\Laravel\LaravelServiceProvider;
use JacyImp\ApiPlatformRateLimiter\Laravel\Middleware\ApiPlatformRateLimitMiddleware;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ApiPlatformLaravelMiddlewareIntegrationTest extends TestCase
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
        $config->set('cache.stores.array', ['driver' => 'array']);
    }

    #[Test]
    public function itRegistersItselfInApiPlatformDefaultMiddleware(): void
    {
        $middleware = $this->application()
            ->make(Repository::class)
            ->get('api-platform.defaults.middleware', []);

        self::assertIsArray($middleware);
        self::assertContains(LaravelServiceProvider::MIDDLEWARE, $middleware);
    }

    #[Test]
    public function itRunsAfterApiPlatformLaravelPopulatesTheOperation(): void
    {
        $apiPlatformMiddleware = 'ApiPlatform\\Laravel\\ApiPlatformMiddleware';

        if (!class_exists($apiPlatformMiddleware)) {
            self::markTestSkipped('api-platform/laravel is not installed.');
        }

        $operationName = 'api_platform_laravel_limited';
        $operation = new Get(
            name: $operationName,
            uriTemplate: '/api-platform-limited',
            extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 1,
                    interval: '1 minute',
                ),
            ],
        );
        $resourceClass = self::class;

        $resourceNames = self::createStub(ResourceNameCollectionFactoryInterface::class);
        $resourceNames
            ->method('create')
            ->willReturn(new ResourceNameCollection([$resourceClass]));

        $resourceMetadata = self::createStub(ResourceMetadataCollectionFactoryInterface::class);
        $resourceMetadata
            ->method('create')
            ->willReturn(new ResourceMetadataCollection(
                $resourceClass,
                [new ApiResource(operations: [$operation])],
            ));

        $this->application()->instance(
            OperationMetadataFactory::class,
            new OperationMetadataFactory($resourceNames, $resourceMetadata),
        );

        $this->application()
            ->make(Router::class)
            ->get('/api-platform-limited', static fn (): array => ['ok' => true])
            ->middleware([
                $apiPlatformMiddleware . ':' . $operationName,
                ApiPlatformRateLimitMiddleware::class,
            ]);

        $this->getJson('/api-platform-limited')->assertOk();
        $this->getJson('/api-platform-limited')->assertStatus(429);
    }

    private function application(): Application
    {
        return $this->app
            ?? throw new \LogicException('Laravel application is not initialized.');
    }
}
