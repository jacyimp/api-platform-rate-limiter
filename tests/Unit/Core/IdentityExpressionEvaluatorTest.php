<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core;

use JacyImp\ApiPlatformRateLimiter\Core\IdentityExpressionEvaluator;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitStrategyRegistry;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\CompositeIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\FirstAvailableIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\IdentityExpression;
use JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core\Fixture\FixedNullableIdentityResolver;
use JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core\Fixture\IdentityA;
use JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core\Fixture\IdentityB;
use JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core\Fixture\IdentityC;
use JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core\Fixture\IdentityD;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IdentityExpressionEvaluator::class)]
final class IdentityExpressionEvaluatorTest extends TestCase
{
    #[Test]
    public function itResolvesSingleIdentity(): void
    {
        $evaluator = $this->evaluator([IdentityB::class => 'user:1']);

        self::assertSame('user:1', $evaluator->evaluate(IdentityB::class));
    }

    #[Test]
    public function itResolvesCompositeIdentityDeterministically(): void
    {
        $evaluator = $this->evaluator([
            IdentityA::class => 'tenant:12',
            IdentityB::class => 'user:34',
        ]);
        $identity = new CompositeIdentity([
            IdentityA::class,
            IdentityB::class,
        ]);

        self::assertSame(
            'composite:9:tenant:127:user:34',
            $evaluator->evaluate($identity),
        );
        self::assertSame(
            $evaluator->evaluate($identity),
            $evaluator->evaluate($identity),
        );
    }

    #[Test]
    public function itUsesFirstAvailableIdentity(): void
    {
        $evaluator = $this->evaluator([
            IdentityC::class => null,
            IdentityB::class => 'user:1',
            IdentityD::class => 'ip:127.0.0.1',
        ]);

        self::assertSame('user:1', $evaluator->evaluate(
            new FirstAvailableIdentity([
                IdentityC::class,
                IdentityB::class,
                IdentityD::class,
            ]),
        ));
    }

    #[Test]
    public function itSupportsNestedExpressions(): void
    {
        $evaluator = $this->evaluator([
            IdentityC::class => null,
            IdentityB::class => 'user:1',
            IdentityA::class => 'tenant:2',
        ]);

        self::assertSame(
            'composite:8:tenant:26:user:1',
            $evaluator->evaluate(new CompositeIdentity([
                IdentityA::class,
                new FirstAvailableIdentity([
                    IdentityC::class,
                    IdentityB::class,
                ]),
            ])),
        );
    }

    #[Test]
    public function itMakesCompositeUnavailableWhenAnyChildIsUnavailable(): void
    {
        $evaluator = $this->evaluator([IdentityA::class => 'tenant:1', IdentityB::class => null]);

        self::assertNull($evaluator->evaluate(new CompositeIdentity([
            IdentityA::class,
            IdentityB::class,
        ])));
    }

    #[Test]
    public function itReturnsNullWhenAllFallbacksAreUnavailable(): void
    {
        $evaluator = $this->evaluator([IdentityB::class => null, IdentityD::class => null]);

        self::assertNull($evaluator->evaluate(new FirstAvailableIdentity([
            IdentityB::class,
            IdentityD::class,
        ])));
    }

    #[Test]
    public function itUsesCollisionSafeCompositeEncoding(): void
    {
        $first = $this->evaluator([IdentityA::class => 'a', IdentityB::class => 'bc'])->evaluate(
            new CompositeIdentity([IdentityA::class, IdentityB::class]),
        );
        $second = $this->evaluator([IdentityA::class => 'ab', IdentityB::class => 'c'])->evaluate(
            new CompositeIdentity([IdentityA::class, IdentityB::class]),
        );

        self::assertNotSame($first, $second);
    }

    #[Test]
    public function itRejectsUnsupportedIdentityExpressions(): void
    {
        $expression = new class implements IdentityExpression {
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unsupported identity expression');

        $this->evaluator([])->evaluate($expression);
    }

    /**
     * @param array<string, string|null> $identities
     */
    private function evaluator(array $identities): IdentityExpressionEvaluator
    {
        $resolvers = [];

        foreach ($identities as $serviceId => $identity) {
            $resolvers[$serviceId] = new FixedNullableIdentityResolver($identity);
        }

        return new IdentityExpressionEvaluator(new RateLimitStrategyRegistry(
            identityResolvers: $resolvers,
            conditions: [],
        ));
    }
}
