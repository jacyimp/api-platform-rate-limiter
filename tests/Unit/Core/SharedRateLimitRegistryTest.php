<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core;

use JacyImp\ApiPlatformRateLimiter\Core\RateLimitDefinition;
use JacyImp\ApiPlatformRateLimiter\Core\SharedRateLimitRegistry;
use JacyImp\ApiPlatformRateLimiter\Exception\UndefinedSharedBucketException;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SharedRateLimitRegistry::class)]
final class SharedRateLimitRegistryTest extends TestCase
{
    #[Test]
    public function itReturnsSharedRateLimitDefinition(): void
    {
        $definition = new RateLimitDefinition(
            limit: 100,
            intervalSeconds: 60,
            policy: RateLimitPolicy::SLIDING_WINDOW,
        );

        $registry = new SharedRateLimitRegistry([
            'catalog' => $definition,
        ]);

        self::assertSame(
            $definition,
            $registry->get('catalog'),
        );
    }

    #[Test]
    public function itRejectsUnknownBucket(): void
    {
        $registry = new SharedRateLimitRegistry([]);

        $this->expectException(UndefinedSharedBucketException::class);
        $this->expectExceptionMessage(
            'Shared rate limit bucket "catalog" is not defined.',
        );

        $registry->get('catalog');
    }
}
