<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Tests\Unit\Core;

use DateTimeImmutable;
use Jacyimp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use Jacyimp\ApiPlatformRateLimiter\Contract\RateLimitBypassInterface;
use Jacyimp\ApiPlatformRateLimiter\Contract\RateLimiterInterface;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitDefinition;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitEnforcer;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitResult;
use Jacyimp\ApiPlatformRateLimiter\Core\ResolvedRateLimit;
use Jacyimp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitEnforcer::class)]
final class RateLimitEnforcerTest extends TestCase
{
    #[Test]
    public function itEnforcesRateLimit(): void
    {
        $rateLimit = $this->rateLimit();

        $identityResolver = self::createMock(
            IdentityResolverInterface::class,
        );
        $identityResolver
            ->expects(self::once())
            ->method('resolve')
            ->willReturn('user:123');

        $bypass = self::createStub(
            RateLimitBypassInterface::class,
        );
        $bypass
            ->method('shouldBypass')
            ->willReturn(false);

        $rateLimiter = self::createMock(
            RateLimiterInterface::class,
        );
        $rateLimiter
            ->expects(self::once())
            ->method('consume')
            ->with(
                $rateLimit,
                'user:123',
                1,
            )
            ->willReturn(
                new RateLimitResult(
                    accepted: true,
                    remaining: 9,
                    retryAfter: new DateTimeImmutable('+1 minute'),
                ),
            );

        $enforcer = new RateLimitEnforcer(
            rateLimiter: $rateLimiter,
            identityResolver: $identityResolver,
            bypass: $bypass,
        );

        self::assertTrue(
            $enforcer->enforce([$rateLimit])->isAccepted(),
        );
    }

    #[Test]
    public function itSkipsBypassedRateLimit(): void
    {
        $rateLimit = $this->rateLimit();

        $identityResolver = self::createStub(
            IdentityResolverInterface::class,
        );
        $identityResolver
            ->method('resolve')
            ->willReturn('user:123');

        $bypass = self::createStub(
            RateLimitBypassInterface::class,
        );
        $bypass
            ->method('shouldBypass')
            ->willReturn(true);

        $rateLimiter = self::createMock(
            RateLimiterInterface::class,
        );
        $rateLimiter
            ->expects(self::never())
            ->method('consume');

        $enforcer = new RateLimitEnforcer(
            rateLimiter: $rateLimiter,
            identityResolver: $identityResolver,
            bypass: $bypass,
        );

        self::assertTrue(
            $enforcer->enforce([$rateLimit])->isAccepted(),
        );
    }

    #[Test]
    public function itDoesNotResolveIdentityWithoutRateLimits(): void
    {
        $identityResolver = self::createMock(
            IdentityResolverInterface::class,
        );
        $identityResolver
            ->expects(self::never())
            ->method('resolve');

        $enforcer = new RateLimitEnforcer(
            rateLimiter: self::createStub(
                RateLimiterInterface::class,
            ),
            identityResolver: $identityResolver,
            bypass: self::createStub(
                RateLimitBypassInterface::class,
            ),
        );

        self::assertTrue(
            $enforcer->enforce([])->isAccepted(),
        );
    }

    #[Test]
    public function itStopsAfterRejectedRateLimit(): void
    {
        $first = $this->rateLimit('operation:product_get');
        $second = $this->rateLimit('shared:catalog');

        $identityResolver = self::createStub(
            IdentityResolverInterface::class,
        );
        $identityResolver
            ->method('resolve')
            ->willReturn('user:123');

        $bypass = self::createStub(
            RateLimitBypassInterface::class,
        );
        $bypass
            ->method('shouldBypass')
            ->willReturn(false);

        $rateLimiter = self::createMock(
            RateLimiterInterface::class,
        );

        $rateLimiter
            ->expects(self::once())
            ->method('consume')
            ->with(
                $first,
                'user:123',
                1,
            )
            ->willReturn(
                new RateLimitResult(
                    accepted: false,
                    remaining: 0,
                    retryAfter: new DateTimeImmutable('+1 minute'),
                ),
            );

        $enforcer = new RateLimitEnforcer(
            rateLimiter: $rateLimiter,
            identityResolver: $identityResolver,
            bypass: $bypass,
        );

        self::assertFalse(
            $enforcer
                ->enforce([$first, $second])
                ->isAccepted(),
        );
    }

    private function rateLimit(
        string $bucket = 'operation:product_get',
    ): ResolvedRateLimit {
        return new ResolvedRateLimit(
            bucket: $bucket,
            definition: new RateLimitDefinition(
                limit: 10,
                intervalSeconds: 60,
                policy: RateLimitPolicy::SLIDING_WINDOW,
            ),
        );
    }
}
