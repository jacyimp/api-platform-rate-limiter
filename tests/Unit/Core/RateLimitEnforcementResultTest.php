<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Tests\Unit\Core;

use DateTimeImmutable;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitConsumption;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitDefinition;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitEnforcementResult;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitResult;
use Jacyimp\ApiPlatformRateLimiter\Core\ResolvedRateLimit;
use Jacyimp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitEnforcementResult::class)]
final class RateLimitEnforcementResultTest extends TestCase
{
    #[Test]
    public function itIsAcceptedWithoutConsumptions(): void
    {
        $result = new RateLimitEnforcementResult([]);

        self::assertTrue($result->isAccepted());
        self::assertNull($result->rejectedConsumption());
    }

    #[Test]
    public function itIsAcceptedWhenAllConsumptionsAreAccepted(): void
    {
        $result = new RateLimitEnforcementResult([
            $this->consumption(
                accepted: true,
                remaining: 9,
                bucket: 'operation:product_get',
            ),
            $this->consumption(
                accepted: true,
                remaining: 99,
                bucket: 'shared:catalog',
            ),
        ]);

        self::assertTrue($result->isAccepted());
        self::assertNull($result->rejectedConsumption());
    }

    #[Test]
    public function itIsRejectedWhenAConsumptionIsRejected(): void
    {
        $result = new RateLimitEnforcementResult([
            $this->consumption(
                accepted: false,
                remaining: 0,
            ),
        ]);

        self::assertFalse($result->isAccepted());
    }

    #[Test]
    public function itExposesRejectedConsumption(): void
    {
        $rejected = $this->consumption(
            accepted: false,
            remaining: 0,
            bucket: 'shared:catalog',
        );

        $result = new RateLimitEnforcementResult([
            $rejected,
        ]);

        self::assertSame(
            $rejected,
            $result->rejectedConsumption(),
        );
    }

    #[Test]
    public function itReturnsFirstRejectedConsumption(): void
    {
        $firstRejected = $this->consumption(
            accepted: false,
            remaining: 0,
            bucket: 'operation:product_get',
        );

        $secondRejected = $this->consumption(
            accepted: false,
            remaining: 0,
            bucket: 'shared:catalog',
        );

        $result = new RateLimitEnforcementResult([
            $firstRejected,
            $secondRejected,
        ]);

        self::assertSame(
            $firstRejected,
            $result->rejectedConsumption(),
        );
    }

    private function consumption(
        bool $accepted,
        int $remaining,
        string $bucket = 'operation:product_get',
    ): RateLimitConsumption {
        return new RateLimitConsumption(
            rateLimit: new ResolvedRateLimit(
                bucket: $bucket,
                definition: new RateLimitDefinition(
                    limit: 100,
                    intervalSeconds: 60,
                    policy: RateLimitPolicy::SLIDING_WINDOW,
                ),
            ),
            result: new RateLimitResult(
                accepted: $accepted,
                remaining: $remaining,
                retryAfter: new DateTimeImmutable('+1 minute'),
            ),
        );
    }
}
