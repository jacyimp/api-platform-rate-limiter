<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Metadata;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicLimit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DynamicLimit::class)]
final class DynamicLimitTest extends TestCase
{
    #[Test]
    public function itStoresResolverServiceId(): void
    {
        self::assertSame(
            'app.limit_resolver',
            (new DynamicLimit('app.limit_resolver'))->resolver,
        );
    }

    #[Test]
    public function itRejectsEmptyResolverServiceId(): void
    {
        $this->expectException(InvalidRateLimitException::class);

        new DynamicLimit(' ');
    }
}
