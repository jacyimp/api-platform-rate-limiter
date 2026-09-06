<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Metadata;

use DateInterval;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use JacyImp\ApiPlatformRateLimiter\Tests\Unit\Metadata\Fixture\MetadataBucketResolver;
use JacyImp\ApiPlatformRateLimiter\Tests\Unit\Metadata\Fixture\MetadataCondition;
use JacyImp\ApiPlatformRateLimiter\Tests\Unit\Metadata\Fixture\MetadataCostResolver;
use JacyImp\ApiPlatformRateLimiter\Tests\Unit\Metadata\Fixture\MetadataIdentityResolver;
use JacyImp\ApiPlatformRateLimiter\Tests\Unit\Metadata\Fixture\MetadataLimitResolver;
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
    public function itAcceptsResolverClassNames(): void
    {
        $rateLimit = new RateLimit(
            limit: 100,
            interval: '1 minute',
            identity: MetadataIdentityResolver::class,
            when: MetadataCondition::class,
        );

        self::assertSame(MetadataIdentityResolver::class, $rateLimit->identity);
        self::assertSame(MetadataCondition::class, $rateLimit->when);
    }

    #[Test]
    public function itAcceptsDynamicValues(): void
    {
        $limit = MetadataLimitResolver::class;
        $bucketResolver = MetadataBucketResolver::class;
        $rateLimit = new RateLimit(
            limit: $limit,
            interval: '1 minute',
            bucketResolver: $bucketResolver,
        );
        self::assertSame($limit, $rateLimit->limit);
        self::assertSame($bucketResolver, $rateLimit->bucketResolver);
    }

    #[Test]
    public function itAcceptsStaticAndDynamicCosts(): void
    {
        $dynamicCost = MetadataCostResolver::class;

        self::assertSame(3, (new RateLimit(limit: 10, interval: '1 minute', cost: 3,))->cost);
        self::assertSame(
            $dynamicCost,
            (new RateLimit(limit: 10, interval: '1 minute', cost: $dynamicCost,))->cost,
        );
    }

    #[Test]
    public function itDefaultsCostToOne(): void
    {
        self::assertSame(1, (new RateLimit(limit: 10, interval: '1 minute',))->cost);
    }

    #[Test]
    public function itRejectsInvalidStaticCost(): void
    {
        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage('Rate limit cost must be greater than zero.');

        new RateLimit(limit: 10, interval: '1 minute', cost: 0,);
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

    #[Test]
    public function itRequiresLimitAndIntervalTogether(): void
    {
        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(
            'Rate limit and interval must either both be set or both be omitted.',
        );

        new RateLimit(limit: 10);
    }

    #[Test]
    public function itRequiresAnInlineLimitOrBucket(): void
    {
        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(
            'An operation-specific rate limit requires a limit and interval.',
        );

        new RateLimit();
    }

    #[Test]
    public function itRejectsAnEmptyBucket(): void
    {
        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage('Rate limit bucket cannot be empty.');

        new RateLimit(bucket: ' ');
    }

    #[Test]
    public function itRejectsABucketAndBucketResolverTogether(): void
    {
        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(
            'Rate limit cannot define both a bucket and a bucket resolver.',
        );

        new RateLimit(
            bucket: 'catalog',
            bucketResolver: MetadataBucketResolver::class,
        );
    }

    #[Test]
    public function itRejectsAnEmptyBucketResolver(): void
    {
        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage('Rate limit bucket resolver cannot be empty.');

        /** @phpstan-ignore argument.type */
        new RateLimit(bucketResolver: ' ');
    }
}
