<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core;

use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitStrategyRegistry;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitStrategyRegistry::class)]
final class RateLimitStrategyRegistryTest extends TestCase
{
    #[Test]
    public function itReturnsStrategiesByClassOrServiceId(): void
    {
        $identityResolver = self::createStub(
            IdentityResolverInterface::class,
        );
        $condition = self::createStub(RateLimitConditionInterface::class);

        $registry = new RateLimitStrategyRegistry(
            ['app.identity' => $identityResolver],
            ['app.condition' => $condition],
        );

        self::assertSame(
            $identityResolver,
            $registry->identityResolver('app.identity'),
        );
        self::assertSame(
            $identityResolver,
            $registry->identityResolver($identityResolver::class),
        );
        self::assertSame(
            $condition,
            $registry->condition('app.condition'),
        );
        self::assertSame(
            $condition,
            $registry->condition($condition::class),
        );
    }

    #[Test]
    public function itRejectsUnknownIdentityResolver(): void
    {
        $registry = new RateLimitStrategyRegistry([], []);

        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(
            'Identity resolver service "app.missing" is not registered.',
        );

        $registry->identityResolver('app.missing');
    }

    #[Test]
    public function itRejectsUnknownCondition(): void
    {
        $registry = new RateLimitStrategyRegistry([], []);

        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(
            'Rate limit condition service "app.missing" is not registered.',
        );

        $registry->condition('app.missing');
    }

    #[Test]
    public function itIndexesNumericallyKeyedStrategiesByClass(): void
    {
        $identityResolver = self::createStub(IdentityResolverInterface::class);
        $registry = new RateLimitStrategyRegistry([$identityResolver], []);

        self::assertSame(
            $identityResolver,
            $registry->identityResolver($identityResolver::class),
        );
    }
}
