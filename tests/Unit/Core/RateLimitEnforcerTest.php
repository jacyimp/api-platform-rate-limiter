<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core;

use DateTimeImmutable;
use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitBypassInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitDefinition;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitEnforcer;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimiterInterface;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitResult;
use JacyImp\ApiPlatformRateLimiter\Core\ResolvedRateLimit;
use JacyImp\ApiPlatformRateLimiter\Event\RateLimitChecking;
use JacyImp\ApiPlatformRateLimiter\Event\RateLimitConsumed;
use JacyImp\ApiPlatformRateLimiter\Event\RateLimitRejected;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(RateLimitEnforcer::class)]
#[CoversClass(RateLimitChecking::class)]
#[CoversClass(RateLimitConsumed::class)]
#[CoversClass(RateLimitRejected::class)]
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
            eventDispatcher: new EventDispatcher(),
        );

        self::assertTrue(
            $enforcer->enforce([$rateLimit])->isAccepted(),
        );
    }

    #[Test]
    public function itConsumesTheResolvedCost(): void
    {
        $rateLimit = $this->rateLimit(cost: 3);
        $identityResolver = self::createStub(IdentityResolverInterface::class);
        $identityResolver->method('resolve')->willReturn('user:123');
        $bypass = self::createStub(RateLimitBypassInterface::class);
        $bypass->method('shouldBypass')->willReturn(false);

        $rateLimiter = self::createMock(RateLimiterInterface::class);
        $rateLimiter->expects(self::once())
            ->method('consume')
            ->with($rateLimit, 'user:123', 3)
            ->willReturn(new RateLimitResult(
                accepted: true,
                remaining: 7,
                retryAfter: new DateTimeImmutable('+1 minute'),
            ));

        $enforcer = new RateLimitEnforcer(
            rateLimiter: $rateLimiter,
            identityResolver: $identityResolver,
            bypass: $bypass,
            eventDispatcher: new EventDispatcher(),
        );

        self::assertTrue($enforcer->enforce([$rateLimit])->isAccepted());
    }

    #[Test]
    public function itSkipsBypassedRateLimit(): void
    {
        $rateLimit = $this->rateLimit();

        $identityResolver = self::createMock(
            IdentityResolverInterface::class,
        );
        $identityResolver
            ->expects(self::never())
            ->method('resolve');

        $bypass = self::createMock(
            RateLimitBypassInterface::class,
        );
        $bypass
            ->expects(self::once())
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
            eventDispatcher: new EventDispatcher(),
        );

        self::assertTrue(
            $enforcer->enforce([$rateLimit])->isAccepted(),
        );
    }

    #[Test]
    public function itUsesStrategiesConfiguredForEachLimit(): void
    {
        $globalIdentityResolver = self::createMock(
            IdentityResolverInterface::class,
        );
        $globalIdentityResolver->expects(self::never())->method('resolve');

        $globalBypass = self::createMock(RateLimitBypassInterface::class);
        $globalBypass
            ->expects(self::exactly(2))
            ->method('shouldBypass')
            ->willReturn(false);

        $firstIdentityResolver = self::createStub(
            IdentityResolverInterface::class,
        );
        $firstIdentityResolver->method('resolve')->willReturn('phone:1');

        $secondIdentityResolver = self::createStub(
            IdentityResolverInterface::class,
        );
        $secondIdentityResolver->method('resolve')->willReturn('api-key:2');

        $firstCondition = self::createStub(
            RateLimitConditionInterface::class,
        );
        $firstCondition->method('shouldApply')->willReturn(true);

        $secondCondition = self::createStub(
            RateLimitConditionInterface::class,
        );
        $secondCondition->method('shouldApply')->willReturn(true);

        $first = $this->rateLimit(
            bucket: 'operation:otp_post',
            identityResolver: $firstIdentityResolver,
            condition: $firstCondition,
        );
        $second = $this->rateLimit(
            bucket: 'shared:api',
            identityResolver: $secondIdentityResolver,
            condition: $secondCondition,
        );

        $rateLimiter = self::createMock(RateLimiterInterface::class);
        $rateLimiter
            ->expects(self::exactly(2))
            ->method('consume')
            ->willReturnCallback(
                static function (
                    ResolvedRateLimit $rateLimit,
                    string $identity,
                ) use ($first): RateLimitResult {
                    self::assertSame(
                        $rateLimit === $first ? 'phone:1' : 'api-key:2',
                        $identity,
                    );

                    return new RateLimitResult(
                        accepted: true,
                        remaining: 1,
                        retryAfter: new DateTimeImmutable('+1 minute'),
                    );
                },
            );

        $enforcer = new RateLimitEnforcer(
            rateLimiter: $rateLimiter,
            identityResolver: $globalIdentityResolver,
            bypass: $globalBypass,
            eventDispatcher: new EventDispatcher(),
        );

        self::assertTrue($enforcer->enforce([$first, $second])->isAccepted());
    }

    #[Test]
    public function itSkipsOnlyTheLimitWhoseConditionDoesNotApply(): void
    {
        $identityResolver = self::createStub(
            IdentityResolverInterface::class,
        );
        $identityResolver->method('resolve')->willReturn('user:123');

        $condition = self::createStub(RateLimitConditionInterface::class);
        $condition->method('shouldApply')->willReturn(false);

        $first = $this->rateLimit(condition: $condition);
        $second = $this->rateLimit('shared:catalog');

        $rateLimiter = self::createMock(RateLimiterInterface::class);
        $rateLimiter
            ->expects(self::once())
            ->method('consume')
            ->with($second, 'user:123', 1)
            ->willReturn(new RateLimitResult(
                accepted: true,
                remaining: 9,
                retryAfter: new DateTimeImmutable('+1 minute'),
            ));

        $globalBypass = self::createStub(RateLimitBypassInterface::class);
        $globalBypass->method('shouldBypass')->willReturn(false);

        $enforcer = new RateLimitEnforcer(
            rateLimiter: $rateLimiter,
            identityResolver: $identityResolver,
            bypass: $globalBypass,
            eventDispatcher: new EventDispatcher(),
        );

        $result = $enforcer->enforce([$first, $second]);

        self::assertTrue($result->isAccepted());
        self::assertCount(1, $result->consumptions);
        self::assertSame($second, $result->consumptions[0]->rateLimit);
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
            eventDispatcher: new EventDispatcher(),
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
            eventDispatcher: new EventDispatcher(),
        );

        self::assertFalse(
            $enforcer
                ->enforce([$first, $second])
                ->isAccepted(),
        );
    }

    #[Test]
    public function itDoesNotRollBackEarlierConsumptionWhenLaterLimitRejects(): void
    {
        $first = $this->rateLimit('operation:product_get');
        $second = $this->rateLimit('shared:catalog');

        $identityResolver = self::createStub(
            IdentityResolverInterface::class,
        );
        $identityResolver
            ->method('resolve')
            ->willReturn('user:123');

        $bypass = self::createMock(
            RateLimitBypassInterface::class,
        );
        $bypass
            ->expects(self::exactly(2))
            ->method('shouldBypass')
            ->willReturn(false);

        $consumed = [];
        $rateLimiter = self::createStub(
            RateLimiterInterface::class,
        );
        $rateLimiter
            ->method('consume')
            ->willReturnCallback(
                static function (
                    ResolvedRateLimit $rateLimit,
                ) use (
                    &$consumed,
                    $second
                ): RateLimitResult {
                    $consumed[] = $rateLimit;

                    return new RateLimitResult(
                        accepted: $rateLimit !== $second,
                        remaining: $rateLimit === $second ? 0 : 9,
                        retryAfter: new DateTimeImmutable('+1 minute'),
                    );
                },
            );

        $enforcer = new RateLimitEnforcer(
            rateLimiter: $rateLimiter,
            identityResolver: $identityResolver,
            bypass: $bypass,
            eventDispatcher: new EventDispatcher(),
        );

        $result = $enforcer->enforce([$first, $second]);

        self::assertFalse($result->isAccepted());
        self::assertSame([$first, $second], $consumed);
        self::assertCount(2, $result->consumptions);
        self::assertTrue($result->consumptions[0]->result->accepted);
        self::assertFalse($result->consumptions[1]->result->accepted);
    }

    #[Test]
    public function itDispatchesObservationalLifecycleEvents(): void
    {
        $first = $this->rateLimit('operation:product_get');
        $second = $this->rateLimit('shared:catalog');
        $retryAfter = new DateTimeImmutable('2030-01-01 00:01:00 UTC');

        $identityResolver = self::createStub(
            IdentityResolverInterface::class,
        );
        $identityResolver->method('resolve')->willReturn('user:123');

        $bypass = self::createStub(RateLimitBypassInterface::class);
        $bypass->method('shouldBypass')->willReturn(false);

        $rateLimiter = self::createStub(RateLimiterInterface::class);
        $rateLimiter
            ->method('consume')
            ->willReturnCallback(
                static fn (ResolvedRateLimit $rateLimit): RateLimitResult =>
                    new RateLimitResult(
                        accepted: $rateLimit === $first,
                        remaining: $rateLimit === $first ? 9 : 0,
                        retryAfter: $retryAfter,
                    ),
            );

        $events = [];
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            RateLimitChecking::class,
            static function (RateLimitChecking $event) use (&$events): void {
                $events[] = $event;
            },
        );
        $eventDispatcher->addListener(
            RateLimitConsumed::class,
            static function (RateLimitConsumed $event) use (&$events): void {
                $events[] = $event;
            },
        );
        $eventDispatcher->addListener(
            RateLimitRejected::class,
            static function (RateLimitRejected $event) use (&$events): void {
                $events[] = $event;
            },
        );

        $enforcer = new RateLimitEnforcer(
            rateLimiter: $rateLimiter,
            identityResolver: $identityResolver,
            bypass: $bypass,
            eventDispatcher: $eventDispatcher,
        );

        $enforcer->enforce([$first, $second]);

        self::assertCount(4, $events);
        self::assertInstanceOf(RateLimitChecking::class, $events[0]);
        self::assertInstanceOf(RateLimitConsumed::class, $events[1]);
        self::assertInstanceOf(RateLimitChecking::class, $events[2]);
        self::assertInstanceOf(RateLimitRejected::class, $events[3]);

        $checking = $events[0];
        self::assertSame('operation:product_get', $checking->bucket);
        self::assertSame('user:123', $checking->identity);
        self::assertSame(10, $checking->limit);
        self::assertSame(60, $checking->intervalSeconds);
        self::assertSame(RateLimitPolicy::SLIDING_WINDOW, $checking->policy);

        $consumed = $events[1];
        self::assertSame(9, $consumed->remaining);
        self::assertSame($retryAfter, $consumed->retryAfter);

        $rejected = $events[3];
        self::assertSame('shared:catalog', $rejected->bucket);
        self::assertSame(0, $rejected->remaining);
        self::assertSame($retryAfter, $rejected->retryAfter);
    }

    private function rateLimit(
        string $bucket = 'operation:product_get',
        ?IdentityResolverInterface $identityResolver = null,
        ?RateLimitConditionInterface $condition = null,
        int $cost = 1,
    ): ResolvedRateLimit {
        return new ResolvedRateLimit(
            bucket: $bucket,
            definition: new RateLimitDefinition(
                limit: 10,
                intervalSeconds: 60,
                policy: RateLimitPolicy::SLIDING_WINDOW,
            ),
            identityResolver: $identityResolver,
            condition: $condition,
            cost: $cost,
        );
    }
}
