<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Symfony;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use JacyImp\ApiPlatformRateLimiter\Metadata\OperationRateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\SharedRateLimit;
use JacyImp\ApiPlatformRateLimiter\Tests\Integration\Symfony\Fixture\TestKernel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ApiPlatformRateLimiterBundleIntegrationTest extends TestCase
{
    private ?TestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;

        parent::tearDown();
    }

    #[Test]
    public function itEnforcesOperationRateLimitThroughSymfonyKernel(): void
    {
        $operation = new Get(
            name: 'limited_get',
            extraProperties: [
                OperationRateLimit::class => new OperationRateLimit(
                    limit: 2,
                    interval: '1 minute',
                ),
            ],
        );

        self::assertSame(
            200,
            $this->handle($operation)->getStatusCode(),
        );

        self::assertSame(
            200,
            $this->handle($operation)->getStatusCode(),
        );

        $this->assertRateLimited($operation);
    }

    #[Test]
    public function itEnforcesSharedRateLimitThroughSymfonyKernel(): void
    {
        $operation = new Get(
            name: 'shared_limited_get',
            extraProperties: [
                SharedRateLimit::class => new SharedRateLimit(
                    'shared_api',
                ),
            ],
        );

        self::assertSame(
            200,
            $this->handle($operation)->getStatusCode(),
        );

        $this->assertRateLimited($operation);
    }

    private function assertRateLimited(
        Operation $operation,
    ): void {
        try {
            $this->handle($operation);

            self::fail(
                'Expected request to be rate limited.',
            );
        } catch (TooManyRequestsHttpException $exception) {
            self::assertSame(
                429,
                $exception->getStatusCode(),
            );

            self::assertSame(
                'Rate limit exceeded.',
                $exception->getMessage(),
            );

            self::assertArrayHasKey(
                'Retry-After',
                $exception->getHeaders(),
            );

            self::assertSame(
                '0',
                $exception->getHeaders()['RateLimit-Remaining'],
            );
        }
    }

    private function handle(
        Operation $operation,
    ): Response {
        $request = Request::create(
            uri: '/limited',
            method: 'GET',
            server: [
                'REMOTE_ADDR' => '192.0.2.1',
            ],
        );

        $request->attributes->set(
            '_api_operation',
            $operation,
        );

        return $this
            ->kernel()
            ->handle(
                request: $request,
                type: HttpKernelInterface::MAIN_REQUEST,
                catch: false,
            );
    }

    private function kernel(): TestKernel
    {
        if ($this->kernel === null) {
            $this->kernel = new TestKernel(
                environment: sprintf(
                    'test%s',
                    bin2hex(random_bytes(8)),
                ),
                debug: false,
            );

            $this->kernel->boot();
        }

        return $this->kernel;
    }
}
