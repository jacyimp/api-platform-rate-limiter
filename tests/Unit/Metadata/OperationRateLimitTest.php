<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Metadata;

use DateInterval;
use InvalidArgumentException;
use JacyImp\ApiPlatformRateLimiter\Metadata\Interval;
use JacyImp\ApiPlatformRateLimiter\Metadata\OperationRateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperationRateLimit::class)]
final class OperationRateLimitTest extends TestCase
{
    #[Test]
    public function itAcceptsStringInterval(): void
    {
        $rateLimit = new OperationRateLimit(
            limit: 100,
            interval: '1 minute',
        );

        self::assertSame(100, $rateLimit->limit);
        self::assertSame('1 minute', $rateLimit->interval);
        self::assertSame(
            RateLimitPolicy::SLIDING_WINDOW,
            $rateLimit->policy,
        );
    }

    #[Test]
    public function itAcceptsDateInterval(): void
    {
        $interval = new DateInterval('PT1M');

        $rateLimit = new OperationRateLimit(
            limit: 100,
            interval: $interval,
        );

        self::assertSame($interval, $rateLimit->interval);
    }

    #[Test]
    public function itAcceptsCustomInterval(): void
    {
        $interval = new Interval(minutes: 1);

        $rateLimit = new OperationRateLimit(
            limit: 100,
            interval: $interval,
        );

        self::assertSame($interval, $rateLimit->interval);
    }

    #[Test]
    public function itAcceptsExplicitPolicy(): void
    {
        $rateLimit = new OperationRateLimit(
            limit: 100,
            interval: '1 minute',
            policy: RateLimitPolicy::FIXED_WINDOW,
        );

        self::assertSame(
            RateLimitPolicy::FIXED_WINDOW,
            $rateLimit->policy,
        );
    }

    #[Test]
    public function itRejectsZeroLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Rate limit must be greater than zero.',
        );

        new OperationRateLimit(
            limit: 0,
            interval: '1 minute',
        );
    }

    #[Test]
    public function itRejectsNegativeLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OperationRateLimit(
            limit: -1,
            interval: '1 minute',
        );
    }
}
