<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Tests\Unit\Core;

use DateTimeImmutable;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitEnforcementResult;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitEnforcementResult::class)]
final class RateLimitEnforcementResultTest extends TestCase
{
    #[Test]
    public function itIsAcceptedWithoutResults(): void
    {
        $result = new RateLimitEnforcementResult([]);

        self::assertTrue($result->isAccepted());
        self::assertNull($result->rejectedResult());
    }

    #[Test]
    public function itIsAcceptedWhenAllResultsAreAccepted(): void
    {
        $result = new RateLimitEnforcementResult([
            new RateLimitResult(
                accepted: true,
                remaining: 9,
                retryAfter: new DateTimeImmutable('+1 minute'),
            ),
            new RateLimitResult(
                accepted: true,
                remaining: 99,
                retryAfter: new DateTimeImmutable('+1 hour'),
            ),
        ]);

        self::assertTrue($result->isAccepted());
        self::assertNull($result->rejectedResult());
    }

    #[Test]
    public function itExposesRejectedResult(): void
    {
        $rejected = new RateLimitResult(
            accepted: false,
            remaining: 0,
            retryAfter: new DateTimeImmutable('+1 minute'),
        );

        $result = new RateLimitEnforcementResult([
            $rejected,
        ]);

        self::assertFalse($result->isAccepted());
        self::assertSame(
            $rejected,
            $result->rejectedResult(),
        );
    }
}
