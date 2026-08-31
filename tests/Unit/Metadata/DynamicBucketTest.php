<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Metadata;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicBucket;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DynamicBucket::class)]
final class DynamicBucketTest extends TestCase
{
    #[Test]
    public function itStoresResolverServiceId(): void
    {
        self::assertSame(
            'app.bucket_resolver',
            (new DynamicBucket('app.bucket_resolver'))->resolver,
        );
    }

    #[Test]
    public function itRejectsEmptyResolverServiceId(): void
    {
        $this->expectException(InvalidRateLimitException::class);

        new DynamicBucket(' ');
    }
}
