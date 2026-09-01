<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Laravel;

use DateTimeImmutable;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejection;
use JacyImp\ApiPlatformRateLimiter\Laravel\LaravelRateLimitRejectionHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LaravelRateLimitRejectionHandler::class)]
final class LaravelRateLimitRejectionHandlerTest extends TestCase
{
    #[Test]
    public function itReturnsAJsonTooManyRequestsResponse(): void
    {
        $response = (new LaravelRateLimitRejectionHandler())->reject(new RateLimitRejection(
            limit: 10,
            remaining: 0,
            retryAfter: new DateTimeImmutable('2030-01-01 03:31:00 +03:30'),
        ));

        self::assertSame(429, $response->getStatusCode());
        self::assertSame(['message' => 'Rate limit exceeded.'], $response->getData(true));
        self::assertSame('10', $response->headers->get('RateLimit-Limit'));
        self::assertSame('0', $response->headers->get('RateLimit-Remaining'));
        self::assertSame(
            'Tue, 01 Jan 2030 00:01:00 GMT',
            $response->headers->get('Retry-After'),
        );
    }
}
