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
    public function itNormalizesEveryPackageIntervalComponent(): void
    {
        self::assertSame(
            93_784,
            (new IntervalNormalizer())->normalize(new Interval(
                days: 1,
                hours: 2,
                minutes: 3,
                seconds: 4,
            )),
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
    public function itNormalizesEveryPhpDateIntervalComponent(): void
    {
        self::assertSame(
            93_784,
            (new IntervalNormalizer())->normalize(new DateInterval('P1DT2H3M4S')),
        );
    }

    #[Test]
    public function itAcceptsAOneSecondPhpInterval(): void
    {
        self::assertSame(
            1,
            (new IntervalNormalizer())->normalize(new DateInterval('PT1S')),
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
    public function itRejectsEveryNegativePhpIntervalComponent(): void
    {
        $setters = [
            'days' => static function (DateInterval $interval): void {
                $interval->d = -1;
            },
            'hours' => static function (DateInterval $interval): void {
                $interval->h = -1;
            },
            'minutes' => static function (DateInterval $interval): void {
                $interval->i = -1;
            },
            'seconds' => static function (DateInterval $interval): void {
                $interval->s = -1;
            },
        ];

        foreach ($setters as $component => $setNegative) {
            $interval = new DateInterval('PT1S');
            $setNegative($interval);

            try {
                (new IntervalNormalizer())->normalize($interval);
                self::fail(sprintf('Negative DateInterval::%s was accepted.', $component));
            } catch (InvalidIntervalException $exception) {
                self::assertSame(
                    'Rate limit interval cannot be negative.',
                    $exception->getMessage(),
                );
            }
        }
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
