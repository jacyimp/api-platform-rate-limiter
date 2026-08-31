<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Symfony;

use DateTimeImmutable;
use DateTimeZone;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejection;
use JacyImp\ApiPlatformRateLimiter\Symfony\SymfonyRateLimitRejectionHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

#[CoversClass(SymfonyRateLimitRejectionHandler::class)]
final class SymfonyRateLimitRejectionHandlerTest extends TestCase
{
    #[Test]
    public function itThrowsATooManyRequestsException(): void
    {
        $retryAfter = new DateTimeImmutable('2030-01-01 03:31:00 +03:30');

        try {
            (new SymfonyRateLimitRejectionHandler())->reject(
                new RateLimitRejection(
                    limit: 10,
                    remaining: 0,
                    retryAfter: $retryAfter,
                ),
            );
        } catch (TooManyRequestsHttpException $exception) {
            self::assertSame(429, $exception->getStatusCode());
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
}
