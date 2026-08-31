<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Symfony;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use JacyImp\ApiPlatformRateLimiter\Exception\RateLimitExceededException;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicCost;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;
use JacyImp\ApiPlatformRateLimiter\Tests\Integration\Symfony\Fixture\FixedCostResolver;
use JacyImp\ApiPlatformRateLimiter\Tests\Integration\Symfony\Fixture\FixedIdentityResolver;
use JacyImp\ApiPlatformRateLimiter\Tests\Integration\Symfony\Fixture\TestKernel;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[RunTestsInSeparateProcesses]
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
    public function itEnforcesRateLimitThroughSymfonyKernel(): void
    {
        $operation = new Get(
            name: 'limited_get',
            extraProperties: [
                RateLimit::class => new RateLimit(
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
                RateLimit::class => new RateLimit(bucket: 'shared_api'),
            ],
        );

        self::assertSame(
            200,
            $this->handle($operation)->getStatusCode(),
        );

        $this->assertRateLimited($operation);
    }

    #[Test]
    public function itConsumesDifferentCostsFromOneSharedBucket(): void
    {
        $lowerCostOperation = new Get(
            name: 'weighted_shared_lower_get',
            extraProperties: [
                RateLimit::class => new RateLimit(
                    bucket: 'weighted_shared',
                    cost: 2,
                ),
            ],
        );
        $dynamicCostOperation = new Get(
            name: 'weighted_shared_dynamic_get',
            extraProperties: [
                RateLimit::class => new RateLimit(
                    bucket: 'weighted_shared',
                    cost: new DynamicCost(FixedCostResolver::class),
                ),
            ],
        );

        self::assertSame(200, $this->handle($lowerCostOperation)->getStatusCode());
        self::assertSame(200, $this->handle($dynamicCostOperation)->getStatusCode());
        $this->assertRateLimited($lowerCostOperation, '1');
    }

    #[Test]
    public function itEnforcesManuallyTaggedProviderThroughSymfonyKernel(): void
    {
        $operation = new Get(
            name: 'manual_provider_get',
        );

        self::assertSame(
            200,
            $this->handle($operation)->getStatusCode(),
        );

        $this->assertRateLimited($operation);
    }

    #[Test]
    public function itEnforcesGlobalRateLimitAcrossOperations(): void
    {
        $this->kernel = new TestKernel(
            environment: sprintf(
                'test%s',
                bin2hex(random_bytes(8)),
            ),
            debug: false,
            globalRateLimit: true,
        );
        $this->kernel->boot();

        self::assertSame(
            200,
            $this->handle(new Get(name: 'first_get'))->getStatusCode(),
        );

        $this->assertRateLimited(new Get(name: 'second_get'));
    }

    #[Test]
    public function itUsesPerLimitIdentityResolverThroughSymfonyKernel(): void
    {
        $operation = new Get(
            name: 'identity_limited_get',
            extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 1,
                    interval: '1 minute',
                    identityResolver: FixedIdentityResolver::class,
                ),
            ],
        );

        self::assertSame(
            200,
            $this->handle($operation, '192.0.2.1')->getStatusCode(),
        );

        try {
            $this->handle($operation, '192.0.2.2');
            self::fail('Expected the shared fixed identity to be limited.');
        } catch (RateLimitExceededException $exception) {
            self::assertSame(429, $exception->getStatusCode());
        }
    }

    #[Test]
    public function itAppliesSharedBucketOnlyWhenConditionMatches(): void
    {
        $operation = new Get(
            name: 'conditional_shared_get',
            extraProperties: [
                RateLimit::class => new RateLimit(bucket: 'conditional_shared',),
            ],
        );

        self::assertSame(200, $this->handle($operation)->getStatusCode());
        self::assertSame(200, $this->handle($operation)->getStatusCode());
    }

    #[Test]
    public function itAppliesOperationLimitOnlyWhenConditionMatches(): void
    {
        $operation = new Get(
            name: 'conditional_get',
            extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 1,
                    interval: '1 minute',
                    when: 'test.never_apply',
                ),
            ],
        );

        self::assertSame(200, $this->handle($operation)->getStatusCode());
        self::assertSame(200, $this->handle($operation)->getStatusCode());
    }

    private function assertRateLimited(
        Operation $operation,
        string $remaining = '0',
    ): void {
        try {
            $this->handle($operation);

            self::fail(
                'Expected request to be rate limited.',
            );
        } catch (RateLimitExceededException $exception) {
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
                $remaining,
                $exception->getHeaders()['RateLimit-Remaining'],
            );
        }
    }

    private function handle(
        Operation $operation,
        string $clientIp = '192.0.2.1',
    ): Response {
        $request = Request::create(
            uri: '/limited',
            method: 'GET',
            server: [
                'REMOTE_ADDR' => $clientIp,
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
