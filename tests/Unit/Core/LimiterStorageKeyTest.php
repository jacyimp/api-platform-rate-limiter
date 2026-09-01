<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core;

use JacyImp\ApiPlatformRateLimiter\Core\LimiterStorageKey;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitDefinition;
use JacyImp\ApiPlatformRateLimiter\Core\ResolvedRateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(LimiterStorageKey::class)]
final class LimiterStorageKeyTest extends TestCase
{
    #[Test]
    public function itIsDeterministicForTheSameConcreteLimiter(): void
    {
        $first = $this->rateLimit('shared:catalog', 100, 60);
        $second = $this->rateLimit('shared:catalog', 100, 60);

        self::assertSame(
            LimiterStorageKey::for($first, 'user:123'),
            LimiterStorageKey::for($second, 'user:123'),
        );
    }

    #[Test]
    public function itIncludesEveryEffectiveStateComponent(): void
    {
        $baseline = LimiterStorageKey::for(
            $this->rateLimit('shared:catalog', 100, 60),
            'user:123',
        );

        self::assertNotSame($baseline, LimiterStorageKey::for(
            $this->rateLimit('shared:orders', 100, 60),
            'user:123',
        ));
        self::assertNotSame($baseline, LimiterStorageKey::for(
            $this->rateLimit('shared:catalog', 100, 60),
            'user:456',
        ));
        self::assertNotSame($baseline, LimiterStorageKey::for(
            $this->rateLimit('shared:catalog', 101, 60),
            'user:123',
        ));
        self::assertNotSame($baseline, LimiterStorageKey::for(
            $this->rateLimit('shared:catalog', 100, 3600),
            'user:123',
        ));
        self::assertNotSame($baseline, LimiterStorageKey::for(
            $this->rateLimit(
                'shared:catalog',
                100,
                60,
                RateLimitPolicy::SLIDING_WINDOW,
            ),
            'user:123',
        ));
    }

    #[Test]
    public function itDoesNotIncludeRequestCost(): void
    {
        $first = $this->rateLimit('shared:catalog', 100, 60, cost: 1);
        $second = $this->rateLimit('shared:catalog', 100, 60, cost: 10);

        self::assertSame(
            LimiterStorageKey::for($first, 'user:123'),
            LimiterStorageKey::for($second, 'user:123'),
        );
    }

    #[Test]
    public function itEncodesVariableLengthComponentsWithoutCollisions(): void
    {
        self::assertNotSame(
            LimiterStorageKey::for($this->rateLimit('a', 100, 60), 'bc'),
            LimiterStorageKey::for($this->rateLimit('ab', 100, 60), 'c'),
        );
    }

    #[Test]
    public function itCannotBeConstructedThroughItsPublicApi(): void
    {
        $reflection = new ReflectionClass(LimiterStorageKey::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());

        $constructor->invoke($reflection->newInstanceWithoutConstructor());
    }

    private function rateLimit(
        string $bucket,
        int $limit,
        int $intervalSeconds,
        RateLimitPolicy $policy = RateLimitPolicy::FIXED_WINDOW,
        int $cost = 1,
    ): ResolvedRateLimit {
        return new ResolvedRateLimit(
            bucket: $bucket,
            definition: new RateLimitDefinition(
                limit: $limit,
                intervalSeconds: $intervalSeconds,
                policy: $policy,
            ),
            cost: $cost,
        );
    }
}
