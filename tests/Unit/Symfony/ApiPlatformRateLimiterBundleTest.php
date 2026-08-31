<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Symfony;

use JacyImp\ApiPlatformRateLimiter\Symfony\ApiPlatformRateLimiterBundle;
use JacyImp\ApiPlatformRateLimiter\Symfony\DependencyInjection\ApiPlatformRateLimiterExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiPlatformRateLimiterBundle::class)]
final class ApiPlatformRateLimiterBundleTest extends TestCase
{
    #[Test]
    public function itProvidesContainerExtension(): void
    {
        $bundle = new ApiPlatformRateLimiterBundle();

        self::assertInstanceOf(
            ApiPlatformRateLimiterExtension::class,
            $bundle->getContainerExtension(),
        );
    }
}
