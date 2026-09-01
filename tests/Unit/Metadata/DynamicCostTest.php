<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Metadata;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicCost;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DynamicCost::class)]
final class DynamicCostTest extends TestCase
{
    #[Test]
    public function itStoresResolverServiceId(): void
    {
        self::assertSame(
            'app.cost_resolver',
            (new DynamicCost('app.cost_resolver'))->resolver,
        );
    }

    #[Test]
    public function itRejectsEmptyResolverServiceId(): void
    {
        $this->expectException(InvalidRateLimitException::class);

        new DynamicCost(' ');
    }
}
