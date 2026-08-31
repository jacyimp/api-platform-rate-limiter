<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Tests\Unit\Core;

use InvalidArgumentException;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitDefinition;
use Jacyimp\ApiPlatformRateLimiter\Core\ResolvedRateLimit;
use Jacyimp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
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

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Rate limit bucket cannot be empty.',
        );

        new ResolvedRateLimit(
            bucket: '',
            definition: $definition,
        );
    }
}
