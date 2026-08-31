<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Tests\Unit\Core;

use Jacyimp\ApiPlatformRateLimiter\Contract\RateLimitBypassInterface;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitBypassChecker;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitDefinition;
use Jacyimp\ApiPlatformRateLimiter\Core\ResolvedRateLimit;
use Jacyimp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitBypassChecker::class)]
final class RateLimitBypassCheckerTest extends TestCase
{
    #[Test]
    public function itDoesNotBypassWithoutBypasses(): void
    {
        $checker = new RateLimitBypassChecker([]);

        self::assertFalse(
            $checker->shouldBypass($this->rateLimit()),
        );
    }

    #[Test]
    public function itDoesNotBypassWhenNoBypassMatches(): void
    {
        $bypass = self::createStub(
            RateLimitBypassInterface::class,
        );
        $bypass
            ->method('shouldBypass')
            ->willReturn(false);

        $checker = new RateLimitBypassChecker([
            $bypass,
        ]);

        self::assertFalse(
            $checker->shouldBypass($this->rateLimit()),
        );
    }

    #[Test]
    public function itBypassesWhenAnyBypassMatches(): void
    {
        $first = self::createStub(
            RateLimitBypassInterface::class,
        );
        $first
            ->method('shouldBypass')
            ->willReturn(false);

        $second = self::createStub(
            RateLimitBypassInterface::class,
        );
        $second
            ->method('shouldBypass')
            ->willReturn(true);

        $checker = new RateLimitBypassChecker([
            $first,
            $second,
        ]);

        self::assertTrue(
            $checker->shouldBypass($this->rateLimit()),
        );
    }

    #[Test]
    public function itStopsCheckingAfterFirstMatch(): void
    {
        $first = self::createMock(
            RateLimitBypassInterface::class,
        );
        $first
            ->expects(self::once())
            ->method('shouldBypass')
            ->willReturn(true);

        $second = self::createMock(
            RateLimitBypassInterface::class,
        );
        $second
            ->expects(self::never())
            ->method('shouldBypass');

        $checker = new RateLimitBypassChecker([
            $first,
            $second,
        ]);

        self::assertTrue(
            $checker->shouldBypass($this->rateLimit()),
        );
    }

    private function rateLimit(): ResolvedRateLimit
    {
        return new ResolvedRateLimit(
            bucket: 'operation:product_get',
            definition: new RateLimitDefinition(
                limit: 100,
                intervalSeconds: 60,
                policy: RateLimitPolicy::SLIDING_WINDOW,
            ),
        );
    }
}
