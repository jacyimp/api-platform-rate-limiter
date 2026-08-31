<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core;

use DateTimeImmutable;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitConsumption;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitDefinition;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitResult;
use JacyImp\ApiPlatformRateLimiter\Core\ResolvedRateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitConsumption::class)]
final class RateLimitConsumptionTest extends TestCase
{
    #[Test]
    public function itStoresRateLimitAndResult(): void
    {
        $rateLimit = new ResolvedRateLimit(
            bucket: 'operation:product_get',
            definition: new RateLimitDefinition(
                limit: 100,
                intervalSeconds: 60,
                policy: RateLimitPolicy::SLIDING_WINDOW,
            ),
        );

        $result = new RateLimitResult(
            accepted: true,
            remaining: 99,
            retryAfter: new DateTimeImmutable('+1 minute'),
        );

        $consumption = new RateLimitConsumption(
            rateLimit: $rateLimit,
            result: $result,
        );

        self::assertSame($rateLimit, $consumption->rateLimit);
        self::assertSame($result, $consumption->result);
    }
}
