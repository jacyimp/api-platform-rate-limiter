<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Tests\Unit\Metadata;

use InvalidArgumentException;
use Jacyimp\ApiPlatformRateLimiter\Metadata\SharedRateLimit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SharedRateLimit::class)]
final class SharedRateLimitTest extends TestCase
{
    #[Test]
    public function storesBucketName(): void
    {
        $rateLimit = new SharedRateLimit('catalog');

        self::assertSame('catalog', $rateLimit->bucket);
    }

    #[Test]
    public function rejectsEmptyBucket(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Shared rate limit bucket cannot be empty.',
        );

        new SharedRateLimit('');
    }

    #[Test]
    public function rejectsWhitespaceOnlyBucket(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SharedRateLimit('   ');
    }
}
