<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core;

use DateTimeImmutable;
use InvalidArgumentException;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitResult::class)]
final class RateLimitResultTest extends TestCase
{
    #[Test]
    public function itStoresConsumptionResult(): void
    {
        $retryAfter = new DateTimeImmutable('+1 minute');

        $result = new RateLimitResult(
            accepted: true,
            remaining: 99,
            retryAfter: $retryAfter,
        );

        self::assertTrue($result->accepted);
        self::assertSame(99, $result->remaining);
        self::assertSame($retryAfter, $result->retryAfter);
    }

    #[Test]
    public function itRepresentsRejectedConsumption(): void
    {
        $retryAfter = new DateTimeImmutable('+1 minute');

        $result = new RateLimitResult(
            accepted: false,
            remaining: 0,
            retryAfter: $retryAfter,
        );

        self::assertFalse($result->accepted);
        self::assertSame(0, $result->remaining);
        self::assertSame($retryAfter, $result->retryAfter);
    }

    #[Test]
    public function itRejectsNegativeRemainingTokens(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Remaining tokens cannot be negative.',
        );

        new RateLimitResult(
            accepted: false,
            remaining: -1,
            retryAfter: new DateTimeImmutable(),
        );
    }
}
