<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Tests\Unit\Core;

use InvalidArgumentException;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitDefinition;
use Jacyimp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitDefinition::class)]
final class RateLimitDefinitionTest extends TestCase
{
    #[Test]
    public function itStoresDefinition(): void
    {
        $definition = new RateLimitDefinition(
            limit: 100,
            intervalSeconds: 60,
            policy: RateLimitPolicy::SLIDING_WINDOW,
        );

        self::assertSame(100, $definition->limit);
        self::assertSame(60, $definition->intervalSeconds);
        self::assertSame(
            RateLimitPolicy::SLIDING_WINDOW,
            $definition->policy,
        );
    }

    #[Test]
    public function itRejectsZeroLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Rate limit must be greater than zero.',
        );

        $definition = new RateLimitDefinition(
            limit: 0,
            intervalSeconds: 60,
            policy: RateLimitPolicy::SLIDING_WINDOW,
        );
    }

    #[Test]
    public function itRejectsNegativeLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $definition = new RateLimitDefinition(
            limit: -1,
            intervalSeconds: 60,
            policy: RateLimitPolicy::SLIDING_WINDOW,
        );
    }

    #[Test]
    public function itRejectsZeroInterval(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Rate limit interval must be greater than zero.',
        );

        $definition = new RateLimitDefinition(
            limit: 100,
            intervalSeconds: 0,
            policy: RateLimitPolicy::SLIDING_WINDOW,
        );
    }

    #[Test]
    public function itRejectsNegativeInterval(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $definition = new RateLimitDefinition(
            limit: 100,
            intervalSeconds: -1,
            policy: RateLimitPolicy::SLIDING_WINDOW,
        );
    }
}
