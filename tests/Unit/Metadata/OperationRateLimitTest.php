<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Tests\Unit\Metadata;

use DateInterval;
use InvalidArgumentException;
use Jacyimp\ApiPlatformRateLimiter\Metadata\Interval;
use Jacyimp\ApiPlatformRateLimiter\Metadata\OperationRateLimit;
use Jacyimp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperationRateLimit::class)]
final class OperationRateLimitTest extends TestCase
{
    #[Test]
    public function acceptsStringInterval(): void
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
    public function acceptsDateInterval(): void
    {
        $interval = new DateInterval('PT1M');

        $rateLimit = new OperationRateLimit(
            limit: 100,
            interval: $interval,
        );

        self::assertSame($interval, $rateLimit->interval);
    }

    #[Test]
    public function acceptsCustomInterval(): void
    {
        $interval = new Interval(minutes: 1);

        $rateLimit = new OperationRateLimit(
            limit: 100,
            interval: $interval,
        );

        self::assertSame($interval, $rateLimit->interval);
    }

    #[Test]
    public function acceptsExplicitPolicy(): void
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
    public function rejectsZeroLimit(): void
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
    public function rejectsNegativeLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OperationRateLimit(
            limit: -1,
            interval: '1 minute',
        );
    }
}
