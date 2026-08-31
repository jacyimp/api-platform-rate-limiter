<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core;

use JacyImp\ApiPlatformRateLimiter\Core\IdentityExpressionEvaluator;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitStrategyRegistry;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\CompositeIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\FirstAvailableIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\Identity;
use JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core\Fixture\FixedNullableIdentityResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IdentityExpressionEvaluator::class)]
final class IdentityExpressionEvaluatorTest extends TestCase
{
    #[Test]
    public function itResolvesSingleIdentity(): void
    {
        $evaluator = $this->evaluator(['user' => 'user:1']);

        self::assertSame('user:1', $evaluator->evaluate(new Identity('user')));
    }

    #[Test]
    public function itResolvesCompositeIdentityDeterministically(): void
    {
        $evaluator = $this->evaluator([
            'tenant' => 'tenant:12',
            'user' => 'user:34',
        ]);
        $identity = new CompositeIdentity([
            new Identity('tenant'),
            new Identity('user'),
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
            'api_key' => null,
            'user' => 'user:1',
            'ip' => 'ip:127.0.0.1',
        ]);

        self::assertSame('user:1', $evaluator->evaluate(
            new FirstAvailableIdentity([
                new Identity('api_key'),
                new Identity('user'),
                new Identity('ip'),
            ]),
        ));
    }

    #[Test]
    public function itSupportsNestedExpressions(): void
    {
        $evaluator = $this->evaluator([
            'api_key' => null,
            'user' => 'user:1',
            'tenant' => 'tenant:2',
        ]);

        self::assertSame(
            'composite:8:tenant:26:user:1',
            $evaluator->evaluate(new CompositeIdentity([
                new Identity('tenant'),
                new FirstAvailableIdentity([
                    new Identity('api_key'),
                    new Identity('user'),
                ]),
            ])),
        );
    }

    #[Test]
    public function itMakesCompositeUnavailableWhenAnyChildIsUnavailable(): void
    {
        $evaluator = $this->evaluator(['tenant' => 'tenant:1', 'user' => null]);

        self::assertNull($evaluator->evaluate(new CompositeIdentity([
            new Identity('tenant'),
            new Identity('user'),
        ])));
    }

    #[Test]
    public function itReturnsNullWhenAllFallbacksAreUnavailable(): void
    {
        $evaluator = $this->evaluator(['user' => null, 'ip' => null]);

        self::assertNull($evaluator->evaluate(new FirstAvailableIdentity([
            new Identity('user'),
            new Identity('ip'),
        ])));
    }

    #[Test]
    public function itUsesCollisionSafeCompositeEncoding(): void
    {
        $first = $this->evaluator(['a' => 'a|b', 'b' => 'c'])->evaluate(
            new CompositeIdentity([new Identity('a'), new Identity('b')]),
        );
        $second = $this->evaluator(['a' => 'a', 'b' => 'b|c'])->evaluate(
            new CompositeIdentity([new Identity('a'), new Identity('b')]),
        );

        self::assertNotSame($first, $second);
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
