<?php

declare(strict_types=1);

namespace Core;

use DateInterval;
use JacyImp\ApiPlatformRateLimiter\Core\IntervalNormalizer;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidIntervalException;
use JacyImp\ApiPlatformRateLimiter\Metadata\Interval;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IntervalNormalizer::class)]
final class IntervalNormalizerTest extends TestCase
{
    #[Test]
    public function itNormalizesPackageInterval(): void
    {
        $normalizer = new IntervalNormalizer();

        self::assertSame(
            90,
            $normalizer->normalize(
                new Interval(minutes: 1, seconds: 30),
            ),
        );
    }

    #[Test]
    public function itNormalizesPhpDateInterval(): void
    {
        $normalizer = new IntervalNormalizer();

        self::assertSame(
            3_600,
            $normalizer->normalize(
                new DateInterval('PT1H'),
            ),
        );
    }

    #[Test]
    public function itNormalizesStringInterval(): void
    {
        $normalizer = new IntervalNormalizer();

        self::assertSame(
            300,
            $normalizer->normalize('5 minutes'),
        );
    }

    #[Test]
    public function itRejectsAnEmptyString(): void
    {
        $this->expectException(InvalidIntervalException::class);
        $this->expectExceptionMessage('Rate limit interval cannot be empty.');

        (new IntervalNormalizer())->normalize(' ');
    }

    #[Test]
    public function itRejectsAnInvalidString(): void
    {
        $this->expectException(InvalidIntervalException::class);
        $this->expectExceptionMessage('Invalid rate limit interval');

        set_error_handler(static fn (): bool => true);

        try {
            (new IntervalNormalizer())->normalize('not an interval');
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function itRejectsMonthsAndYears(): void
    {
        $this->expectException(InvalidIntervalException::class);
        $this->expectExceptionMessage(
            'Rate limit intervals cannot contain months or years.',
        );

        (new IntervalNormalizer())->normalize(new DateInterval('P1M'));
    }

    #[Test]
    public function itRejectsNegativeIntervals(): void
    {
        $interval = new DateInterval('PT1S');
        $interval->invert = 1;

        $this->expectException(InvalidIntervalException::class);
        $this->expectExceptionMessage('Rate limit interval cannot be negative.');

        (new IntervalNormalizer())->normalize($interval);
    }

    #[Test]
    public function itRejectsFractionalSeconds(): void
    {
        $interval = new DateInterval('PT0S');
        $interval->f = 0.5;

        $this->expectException(InvalidIntervalException::class);
        $this->expectExceptionMessage(
            'Rate limit interval cannot contain fractional seconds.',
        );

        (new IntervalNormalizer())->normalize($interval);
    }

    #[Test]
    public function itRejectsZeroSeconds(): void
    {
        $this->expectException(InvalidIntervalException::class);
        $this->expectExceptionMessage(
            'Rate limit interval must be greater than zero.',
        );

        (new IntervalNormalizer())->normalize(new DateInterval('PT0S'));
    }
}
