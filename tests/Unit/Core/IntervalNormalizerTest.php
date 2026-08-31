<?php

declare(strict_types=1);

namespace Core;

use DateInterval;
use Jacyimp\ApiPlatformRateLimiter\Core\IntervalNormalizer;
use Jacyimp\ApiPlatformRateLimiter\Metadata\Interval;
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
}
