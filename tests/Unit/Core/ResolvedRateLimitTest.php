<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core;

use JacyImp\ApiPlatformRateLimiter\Core\RateLimitDefinition;
use JacyImp\ApiPlatformRateLimiter\Core\ResolvedRateLimit;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResolvedRateLimit::class)]
final class ResolvedRateLimitTest extends TestCase
{
    #[Test]
    public function itStoresBucketAndDefinition(): void
    {
        $definition = new RateLimitDefinition(
            limit: 100,
            intervalSeconds: 60,
            policy: RateLimitPolicy::SLIDING_WINDOW,
        );

        $resolved = new ResolvedRateLimit(
            bucket: 'operation:product_get',
            definition: $definition,
        );

        self::assertSame('operation:product_get', $resolved->bucket);
        self::assertSame($definition, $resolved->definition);
    }

    #[Test]
    public function itRejectsEmptyBucket(): void
    {
        $definition = new RateLimitDefinition(
            limit: 100,
            intervalSeconds: 60,
            policy: RateLimitPolicy::SLIDING_WINDOW,
        );

        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(
            'Rate limit bucket cannot be empty.',
        );

        new ResolvedRateLimit(
            bucket: '',
            definition: $definition,
        );
    }

    #[Test]
    public function itRejectsNonPositiveCost(): void
    {
        $definition = new RateLimitDefinition(
            limit: 100,
            intervalSeconds: 60,
            policy: RateLimitPolicy::SLIDING_WINDOW,
        );

        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(
            'Resolved rate limit cost must be greater than zero.',
        );

        new ResolvedRateLimit(
            bucket: 'operation:product_get',
            definition: $definition,
            cost: 0,
        );
    }
}
