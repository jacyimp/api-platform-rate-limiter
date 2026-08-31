<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Metadata;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\CompositeIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\FirstAvailableIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\Identity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IdentityExpressionTest extends TestCase
{
    #[Test]
    public function itRejectsEmptyResolverServiceId(): void
    {
        $this->expectException(InvalidRateLimitException::class);

        new Identity(' ');
    }

    #[Test]
    public function itRejectsEmptyCompositeIdentity(): void
    {
        $this->expectException(InvalidRateLimitException::class);

        new CompositeIdentity([]);
    }

    #[Test]
    public function itRejectsEmptyFirstAvailableIdentity(): void
    {
        $this->expectException(InvalidRateLimitException::class);

        new FirstAvailableIdentity([]);
    }
}
