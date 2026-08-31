<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Tests\Unit\Symfony\EventListener;

use ApiPlatform\Metadata\Get;
use DateTimeImmutable;
use DateTimeZone;
use Jacyimp\ApiPlatformRateLimiter\ApiPlatform\RateLimitMetadataExtractor;
use Jacyimp\ApiPlatformRateLimiter\ApiPlatform\RateLimitResolver;
use Jacyimp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use Jacyimp\ApiPlatformRateLimiter\Contract\RateLimitBypassInterface;
use Jacyimp\ApiPlatformRateLimiter\Contract\RateLimiterInterface;
use Jacyimp\ApiPlatformRateLimiter\Core\IntervalNormalizer;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitEnforcer;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitResult;
use Jacyimp\ApiPlatformRateLimiter\Core\SharedRateLimitRegistry;
use Jacyimp\ApiPlatformRateLimiter\Metadata\OperationRateLimit;
use Jacyimp\ApiPlatformRateLimiter\Symfony\EventListener\ApiPlatformRateLimitListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(ApiPlatformRateLimitListener::class)]
final class ApiPlatformRateLimitListenerTest extends TestCase
{
    #[Test]
    public function itIgnoresRequestWithoutApiPlatformOperation(): void
    {
        $rateLimiter = self::createMock(
            RateLimiterInterface::class,
        );

        $rateLimiter
            ->expects(self::never())
            ->method('consume');

        $listener = $this->listener($rateLimiter);

        $listener->onKernelRequest(
            $this->requestEvent(
                new Request(),
            ),
        );
    }

    #[Test]
    public function itAllowsAcceptedRequest(): void
    {
        $rateLimiter = self::createMock(
            RateLimiterInterface::class,
        );

        $rateLimiter
            ->expects(self::once())
            ->method('consume')
            ->willReturn(
                new RateLimitResult(
                    accepted: true,
                    remaining: 9,
                    retryAfter: new DateTimeImmutable(
                        '2030-01-01 00:01:00 UTC',
                    ),
                ),
            );

        $request = new Request();

        $request->attributes->set(
            '_api_operation',
            new Get(
                name: 'product_get',
                extraProperties: [
                    OperationRateLimit::class => new OperationRateLimit(
                        limit: 10,
                        interval: '1 minute',
                    ),
                ],
            ),
        );

        $this
            ->listener($rateLimiter)
            ->onKernelRequest(
                $this->requestEvent($request),
            );
    }

    #[Test]
    public function itRejectsRateLimitedRequest(): void
    {
        $retryAfter = new DateTimeImmutable(
            '2030-01-01 00:01:00 UTC',
        );

        $rateLimiter = self::createMock(
            RateLimiterInterface::class,
        );

        $rateLimiter
            ->expects(self::once())
            ->method('consume')
            ->willReturn(
                new RateLimitResult(
                    accepted: false,
                    remaining: 0,
                    retryAfter: $retryAfter,
                ),
            );

        $request = new Request();

        $request->attributes->set(
            '_api_operation',
            new Get(
                name: 'product_get',
                extraProperties: [
                    OperationRateLimit::class => new OperationRateLimit(
                        limit: 10,
                        interval: '1 minute',
                    ),
                ],
            ),
        );

        try {
            $this
                ->listener($rateLimiter)
                ->onKernelRequest(
                    $this->requestEvent($request),
                );

            self::fail(
                'Expected rate limit exception to be thrown.',
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

            self::assertSame(
                $retryAfter
                    ->setTimezone(new DateTimeZone('GMT'))
                    ->format('D, d M Y H:i:s \G\M\T'),
                $exception->getHeaders()['Retry-After'],
            );

            self::assertSame(
                '10',
                $exception->getHeaders()['RateLimit-Limit'],
            );

            self::assertSame(
                '0',
                $exception->getHeaders()['RateLimit-Remaining'],
            );
        }
    }

    #[Test]
    public function itIgnoresSubRequests(): void
    {
        $rateLimiter = self::createMock(
            RateLimiterInterface::class,
        );

        $rateLimiter
            ->expects(self::never())
            ->method('consume');

        $request = new Request();

        $request->attributes->set(
            '_api_operation',
            new Get(
                name: 'product_get',
                extraProperties: [
                    OperationRateLimit::class => new OperationRateLimit(
                        limit: 10,
                        interval: '1 minute',
                    ),
                ],
            ),
        );

        $this
            ->listener($rateLimiter)
            ->onKernelRequest(
                new RequestEvent(
                    kernel: self::createStub(
                        HttpKernelInterface::class,
                    ),
                    request: $request,
                    requestType: HttpKernelInterface::SUB_REQUEST,
                ),
            );
    }

    private function listener(
        RateLimiterInterface $rateLimiter,
    ): ApiPlatformRateLimitListener {
        $identityResolver = self::createStub(
            IdentityResolverInterface::class,
        );

        $identityResolver
            ->method('resolve')
            ->willReturn('user:123');

        $bypass = self::createStub(
            RateLimitBypassInterface::class,
        );

        $bypass
            ->method('shouldBypass')
            ->willReturn(false);

        return new ApiPlatformRateLimitListener(
            rateLimitResolver: new RateLimitResolver(
                metadataExtractor: new RateLimitMetadataExtractor(),
                intervalNormalizer: new IntervalNormalizer(),
                sharedRateLimitRegistry: new SharedRateLimitRegistry([]),
            ),
            rateLimitEnforcer: new RateLimitEnforcer(
                rateLimiter: $rateLimiter,
                identityResolver: $identityResolver,
                bypass: $bypass,
            ),
        );
    }

    private function requestEvent(
        Request $request,
    ): RequestEvent {
        return new RequestEvent(
            kernel: self::createStub(
                HttpKernelInterface::class,
            ),
            request: $request,
            requestType: HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
