<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Tests\Unit\Metadata;

use InvalidArgumentException;
use Jacyimp\ApiPlatformRateLimiter\Metadata\Interval;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Interval::class)]
final class IntervalTest extends TestCase
{
    #[Test]
    public function storesIntervalComponents(): void
    {
        $interval = new Interval(
            days: 1,
            hours: 2,
            minutes: 30,
            seconds: 15,
        );

        self::assertSame(1, $interval->days);
        self::assertSame(2, $interval->hours);
        self::assertSame(30, $interval->minutes);
        self::assertSame(15, $interval->seconds);
    }

    #[Test]
    public function rejectsZeroInterval(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Interval must be greater than zero.',
        );

        new Interval();
    }

    #[Test]
    #[DataProvider('negativeIntervals')]
    public function rejectsNegativeValues(
        int $days,
        int $hours,
        int $minutes,
        int $seconds,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Interval values cannot be negative.',
        );

        new Interval(
            days: $days,
            hours: $hours,
            minutes: $minutes,
            seconds: $seconds,
        );
    }

    public static function negativeIntervals(): iterable
    {
        yield 'days' => [-1, 0, 0, 0];
        yield 'hours' => [0, -1, 0, 0];
        yield 'minutes' => [0, 0, -1, 0];
        yield 'seconds' => [0, 0, 0, -1];
    }
}
