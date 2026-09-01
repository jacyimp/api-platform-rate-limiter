<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Laravel;

use Illuminate\Contracts\Cache\Repository;
use JacyImp\ApiPlatformRateLimiter\Laravel\LaravelCacheStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LaravelCacheStorage::class)]
final class LaravelCacheStorageTest extends TestCase
{
    #[Test]
    public function itDeletesNamespacedLimiterState(): void
    {
        $cache = self::createMock(Repository::class);
        $cache
            ->expects(self::once())
            ->method('forget')
            ->with('api-platform-rate-limiter:' . sha1('customer:1'));

        (new LaravelCacheStorage($cache))->delete('customer:1');
    }
}
