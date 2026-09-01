<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core;

use JacyImp\ApiPlatformRateLimiter\Contract\BucketResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\CostResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\LimitResolverInterface;
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
        $bucketResolver = self::createStub(BucketResolverInterface::class);
        $limitResolver = self::createStub(LimitResolverInterface::class);
        $costResolver = self::createStub(CostResolverInterface::class);

        $registry = new RateLimitStrategyRegistry(
            ['app.identity' => $identityResolver],
            ['app.condition' => $condition],
            ['app.bucket' => $bucketResolver],
            ['app.limit' => $limitResolver],
            ['app.cost' => $costResolver],
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
        self::assertSame($bucketResolver, $registry->bucketResolver('app.bucket'));
        self::assertSame($limitResolver, $registry->limitResolver('app.limit'));
        self::assertSame($costResolver, $registry->costResolver('app.cost'));
    }

    #[Test]
    public function itRejectsUnknownIdentityResolver(): void
    {
        $registry = new RateLimitStrategyRegistry([], []);

        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Identity resolver service "app.missing" is not registered. '
                . 'Ensure it implements %s and is autoconfigured or tagged.',
                IdentityResolverInterface::class,
            ),
        );

        $registry->identityResolver('app.missing');
    }

    #[Test]
    public function itRejectsUnknownCondition(): void
    {
        $registry = new RateLimitStrategyRegistry([], []);

        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Rate limit condition service "app.missing" is not registered. '
                . 'Ensure it implements %s and is autoconfigured or tagged.',
                RateLimitConditionInterface::class,
            ),
        );

        $registry->condition('app.missing');
    }

    #[Test]
    public function itRejectsUnknownBucketResolverWithActionableDetails(): void
    {
        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(sprintf(
            'Bucket resolver service "app.missing" is not registered. '
            . 'Ensure it implements %s and is autoconfigured or tagged.',
            BucketResolverInterface::class,
        ));

        (new RateLimitStrategyRegistry([], []))->bucketResolver('app.missing');
    }

    #[Test]
    public function itRejectsUnknownLimitResolverWithActionableDetails(): void
    {
        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(sprintf(
            'Limit resolver service "app.missing" is not registered. '
            . 'Ensure it implements %s and is autoconfigured or tagged.',
            LimitResolverInterface::class,
        ));

        (new RateLimitStrategyRegistry([], []))->limitResolver('app.missing');
    }

    #[Test]
    public function itRejectsUnknownCostResolverWithActionableDetails(): void
    {
        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(sprintf(
            'Cost resolver service "app.missing" is not registered. '
            . 'Ensure it implements %s and is autoconfigured or tagged.',
            CostResolverInterface::class,
        ));

        (new RateLimitStrategyRegistry([], []))->costResolver('app.missing');
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

    #[Test]
    public function itIndexesEveryNumericallyKeyedStrategy(): void
    {
        $first = new class implements IdentityResolverInterface {
            public function resolve(): string
            {
                return 'first';
            }
        };
        $second = new class implements IdentityResolverInterface {
            public function resolve(): string
            {
                return 'second';
            }
        };
        $registry = new RateLimitStrategyRegistry([$first, $second], []);

        self::assertSame($first, $registry->identityResolver($first::class));
        self::assertSame($second, $registry->identityResolver($second::class));
    }
}
