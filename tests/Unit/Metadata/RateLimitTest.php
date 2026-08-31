<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Metadata;

use DateInterval;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicBucket;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\Interval;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimit::class)]
final class RateLimitTest extends TestCase
{
    #[Test]
    public function itAcceptsStringInterval(): void
    {
        $rateLimit = new RateLimit(
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

        $rateLimit = new RateLimit(
            limit: 100,
            interval: $interval,
        );

        self::assertSame($interval, $rateLimit->interval);
    }

    #[Test]
    public function itAcceptsCustomInterval(): void
    {
        $interval = new Interval(minutes: 1);

        $rateLimit = new RateLimit(
            limit: 100,
            interval: $interval,
        );

        self::assertSame($interval, $rateLimit->interval);
    }

    #[Test]
    public function itAcceptsExplicitPolicy(): void
    {
        $rateLimit = new RateLimit(
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
    public function itAcceptsPerLimitServiceIds(): void
    {
        $rateLimit = new RateLimit(
            limit: 100,
            interval: '1 minute',
            identityResolver: 'app.identity_resolver',
            when: 'app.condition',
        );

        self::assertSame('app.identity_resolver', $rateLimit->identityResolver);
        self::assertSame('app.condition', $rateLimit->when);
    }

    #[Test]
    public function itAcceptsDynamicValues(): void
    {
        $limit = new DynamicLimit('app.limit_resolver');
        $bucket = new DynamicBucket('app.bucket_resolver');
        $rateLimit = new RateLimit(limit: $limit, interval: '1 minute', bucket: $bucket,);
        self::assertSame($limit, $rateLimit->limit);
        self::assertSame($bucket, $rateLimit->bucket);
    }

    #[Test]
    public function itAcceptsConfiguredBucketReference(): void
    {
        $rateLimit = new RateLimit(bucket: 'catalog');
        self::assertNull($rateLimit->limit);
        self::assertNull($rateLimit->interval);
        self::assertSame('catalog', $rateLimit->bucket);
    }
    #[Test]
    public function itRejectsEmptyIdentityResolverServiceId(): void
    {
        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(
            'Identity resolver service ID cannot be empty.',
        );

        new RateLimit(
            limit: 100,
            interval: '1 minute',
            identityResolver: ' ',
        );
    }

    #[Test]
    public function itRejectsEmptyConditionServiceId(): void
    {
        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(
            'Rate limit condition service ID cannot be empty.',
        );

        new RateLimit(
            limit: 100,
            interval: '1 minute',
            when: '',
        );
    }

    #[Test]
    public function itRejectsZeroLimit(): void
    {
        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(
            'Rate limit must be greater than zero.',
        );

        new RateLimit(
            limit: 0,
            interval: '1 minute',
        );
    }

    #[Test]
    public function itRejectsNegativeLimit(): void
    {
        $this->expectException(InvalidRateLimitException::class);

        new RateLimit(
            limit: -1,
            interval: '1 minute',
        );
    }
}
