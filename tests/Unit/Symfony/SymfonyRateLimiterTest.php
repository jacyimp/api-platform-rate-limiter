<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Symfony;

use JacyImp\ApiPlatformRateLimiter\Core\RateLimitDefinition;
use JacyImp\ApiPlatformRateLimiter\Core\ResolvedRateLimit;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use JacyImp\ApiPlatformRateLimiter\Symfony\SymfonyRateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[CoversClass(SymfonyRateLimiter::class)]
final class SymfonyRateLimiterTest extends TestCase
{
    private SymfonyRateLimiter $limiter;

    protected function setUp(): void
    {
        $this->limiter = new SymfonyRateLimiter(
            new InMemoryStorage(),
        );
    }

    #[Test]
    public function itConsumesTokens(): void
    {
        $rateLimit = $this->rateLimit(
            bucket: 'operation:product_get',
            limit: 2,
        );

        $first = $this->limiter->consume(
            rateLimit: $rateLimit,
            identity: 'user:123',
        );

        $second = $this->limiter->consume(
            rateLimit: $rateLimit,
            identity: 'user:123',
        );

        $third = $this->limiter->consume(
            rateLimit: $rateLimit,
            identity: 'user:123',
        );

        self::assertTrue($first->accepted);
        self::assertSame(1, $first->remaining);

        self::assertTrue($second->accepted);
        self::assertSame(0, $second->remaining);

        self::assertFalse($third->accepted);
        self::assertSame(0, $third->remaining);
    }

    #[Test]
    public function itConsumesMultipleTokens(): void
    {
        $result = $this->limiter->consume(
            rateLimit: $this->rateLimit(
                bucket: 'operation:export',
                limit: 5,
            ),
            identity: 'user:123',
            tokens: 3,
        );

        self::assertTrue($result->accepted);
        self::assertSame(2, $result->remaining);
    }

    #[Test]
    public function itIsolatesIdentities(): void
    {
        $rateLimit = $this->rateLimit(
            bucket: 'operation:product_get',
            limit: 1,
        );

        $first = $this->limiter->consume(
            rateLimit: $rateLimit,
            identity: 'user:123',
        );

        $second = $this->limiter->consume(
            rateLimit: $rateLimit,
            identity: 'user:456',
        );

        self::assertTrue($first->accepted);
        self::assertTrue($second->accepted);
    }

    #[Test]
    public function itIsolatesBuckets(): void
    {
        $first = $this->limiter->consume(
            rateLimit: $this->rateLimit(
                bucket: 'operation:product_get',
                limit: 1,
            ),
            identity: 'user:123',
        );

        $second = $this->limiter->consume(
            rateLimit: $this->rateLimit(
                bucket: 'operation:product_post',
                limit: 1,
            ),
            identity: 'user:123',
        );

        self::assertTrue($first->accepted);
        self::assertTrue($second->accepted);
    }

    #[Test]
    public function itKeepsOperationSharedAndGlobalNamespacesIndependent(): void
    {
        foreach (
            [
                'operation:catalog',
                'shared:catalog',
                'global:catalog',
                'global:api:free',
                'global:api:premium',
            ] as $bucket
        ) {
            $result = $this->limiter->consume(
                $this->rateLimit($bucket, 1),
                'user:123',
            );

            self::assertTrue($result->accepted);
        }
    }

    #[Test]
    public function itPartitionsResolvedDynamicBucketValues(): void
    {
        $free = $this->rateLimit('shared:plan:free', 1);
        $sameFree = $this->rateLimit('shared:plan:free', 1);
        $premium = $this->rateLimit('shared:plan:premium', 1);

        self::assertTrue($this->limiter->consume($free, 'user:123')->accepted);
        self::assertFalse(
            $this->limiter->consume($sameFree, 'user:123')->accepted,
        );
        self::assertTrue(
            $this->limiter->consume($premium, 'user:123')->accepted,
        );
    }

    #[Test]
    public function itSharesStateForEquivalentConcreteLimiters(): void
    {
        $first = $this->limiter->consume(
            $this->rateLimit('shared:catalog', 2),
            'user:123',
        );
        $second = $this->limiter->consume(
            $this->rateLimit('shared:catalog', 2),
            'user:123',
        );

        self::assertSame(1, $first->remaining);
        self::assertSame(0, $second->remaining);
    }

    #[Test]
    public function itIsolatesDifferentLimits(): void
    {
        $this->limiter->consume(
            $this->rateLimit('shared:catalog', 1),
            'user:123',
        );
        $result = $this->limiter->consume(
            $this->rateLimit('shared:catalog', 2),
            'user:123',
        );

        self::assertTrue($result->accepted);
        self::assertSame(1, $result->remaining);
    }

    #[Test]
    public function itIsolatesDifferentIntervals(): void
    {
        $this->limiter->consume(
            $this->rateLimit('shared:catalog', 1, intervalSeconds: 60),
            'user:123',
        );
        $result = $this->limiter->consume(
            $this->rateLimit('shared:catalog', 1, intervalSeconds: 3600),
            'user:123',
        );

        self::assertTrue($result->accepted);
    }

    #[Test]
    public function itIsolatesDifferentPolicies(): void
    {
        $this->limiter->consume(
            $this->rateLimit(
                'shared:catalog',
                1,
                policy: RateLimitPolicy::FIXED_WINDOW,
            ),
            'user:123',
        );
        $result = $this->limiter->consume(
            $this->rateLimit(
                'shared:catalog',
                1,
                policy: RateLimitPolicy::SLIDING_WINDOW,
            ),
            'user:123',
        );

        self::assertTrue($result->accepted);
    }

    #[Test]
    public function itUsesOneCounterForDifferentRequestCosts(): void
    {
        $cheap = $this->rateLimit('shared:catalog', 11, cost: 1);
        $expensive = $this->rateLimit('shared:catalog', 11, cost: 10);

        $first = $this->limiter->consume($cheap, 'user:123', $cheap->cost);
        $second = $this->limiter->consume(
            $expensive,
            'user:123',
            $expensive->cost,
        );
        $third = $this->limiter->consume($cheap, 'user:123', $cheap->cost);

        self::assertSame(10, $first->remaining);
        self::assertTrue($second->accepted);
        self::assertSame(0, $second->remaining);
        self::assertFalse($third->accepted);
    }

    #[Test]
    public function itSupportsSlidingWindowPolicy(): void
    {
        $result = $this->limiter->consume(
            rateLimit: $this->rateLimit(
                bucket: 'operation:product_get',
                limit: 10,
                policy: RateLimitPolicy::SLIDING_WINDOW,
            ),
            identity: 'user:123',
        );

        self::assertTrue($result->accepted);
        self::assertSame(9, $result->remaining);
    }

    #[Test]
    public function itRejectsEmptyIdentity(): void
    {
        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(
            'Rate limit identity cannot be empty.',
        );

        $this->limiter->consume(
            rateLimit: $this->rateLimit(
                bucket: 'operation:product_get',
                limit: 10,
            ),
            identity: '',
        );
    }

    #[Test]
    public function itRejectsZeroTokens(): void
    {
        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(
            'Consumed tokens must be greater than zero.',
        );

        $this->limiter->consume(
            rateLimit: $this->rateLimit(
                bucket: 'operation:product_get',
                limit: 10,
            ),
            identity: 'user:123',
            tokens: 0,
        );
    }

    private function rateLimit(
        string $bucket,
        int $limit,
        RateLimitPolicy $policy = RateLimitPolicy::FIXED_WINDOW,
        int $intervalSeconds = 60,
        int $cost = 1,
    ): ResolvedRateLimit {
        return new ResolvedRateLimit(
            bucket: $bucket,
            definition: new RateLimitDefinition(
                limit: $limit,
                intervalSeconds: $intervalSeconds,
                policy: $policy,
            ),
            cost: $cost,
        );
    }
}
