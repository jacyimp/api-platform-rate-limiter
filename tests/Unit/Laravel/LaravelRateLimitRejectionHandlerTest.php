<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Laravel;

use DateTimeImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejection;
use JacyImp\ApiPlatformRateLimiter\Laravel\LaravelRateLimitRejectionHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LaravelRateLimitRejectionHandler::class)]
final class LaravelRateLimitRejectionHandlerTest extends TestCase
{
    #[Test]
    public function itThrowsAJsonTooManyRequestsResponse(): void
    {
        $exception = $this->captureException(static function (): never {
            (new LaravelRateLimitRejectionHandler())->reject(new RateLimitRejection(
                limit: 10,
                remaining: 0,
                retryAfter: new DateTimeImmutable('2030-01-01 03:31:00 +03:30'),
            ));
        });
        $response = $exception->getResponse();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(429, $response->getStatusCode());
        self::assertSame(['message' => 'Rate limit exceeded.'], $response->getData(true));
        self::assertSame('10', $response->headers->get('RateLimit-Limit'));
        self::assertSame('0', $response->headers->get('RateLimit-Remaining'));
        self::assertSame(
            'Tue, 01 Jan 2030 00:01:00 GMT',
            $response->headers->get('Retry-After'),
        );
    }

    /** @param callable(): void $operation */
    private function captureException(callable $operation): HttpResponseException
    {
        try {
            $operation();
        } catch (HttpResponseException $exception) {
            return $exception;
        }

        self::fail('The rejection handler did not throw a response exception.');
    }
}
