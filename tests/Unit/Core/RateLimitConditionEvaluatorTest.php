<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core;

use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitConditionEvaluator;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitStrategyRegistry;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AllOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AnyOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Not;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\RateLimitCondition;
use JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core\Fixture\MatchingCondition;
use JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core\Fixture\NonMatchingCondition;
use JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core\Fixture\UnusedCondition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitConditionEvaluator::class)]
final class RateLimitConditionEvaluatorTest extends TestCase
{
    #[Test]
    public function itEvaluatesASingleCondition(): void
    {
        $condition = self::createMock(RateLimitConditionInterface::class);
        $condition->expects(self::once())->method('matches')->willReturn(true);

        self::assertTrue($this->evaluator([MatchingCondition::class => $condition])->matches(
            MatchingCondition::class,
        ));
    }

    #[Test]
    public function itEvaluatesDeeplyNestedExpressions(): void
    {
        $true = self::createStub(RateLimitConditionInterface::class);
        $true->method('matches')->willReturn(true);
        $false = self::createStub(RateLimitConditionInterface::class);
        $false->method('matches')->willReturn(false);

        $expression = new AllOf([
            MatchingCondition::class,
            new Not(new AnyOf([
                NonMatchingCondition::class,
                new AllOf([
                    MatchingCondition::class,
                    NonMatchingCondition::class,
                ]),
            ])),
        ]);

        self::assertTrue($this->evaluator([
            MatchingCondition::class => $true,
            NonMatchingCondition::class => $false,
        ])->matches($expression));
    }

    #[Test]
    public function itShortCircuitsAllOfAfterFalse(): void
    {
        $false = self::createMock(RateLimitConditionInterface::class);
        $false->expects(self::once())->method('matches')->willReturn(false);
        $unused = self::createMock(RateLimitConditionInterface::class);
        $unused->expects(self::never())->method('matches');

        self::assertFalse($this->evaluator([
            NonMatchingCondition::class => $false,
            UnusedCondition::class => $unused,
        ])->matches(new AllOf([
            NonMatchingCondition::class,
            UnusedCondition::class,
        ])));
    }

    #[Test]
    public function itShortCircuitsAnyOfAfterTrue(): void
    {
        $true = self::createMock(RateLimitConditionInterface::class);
        $true->expects(self::once())->method('matches')->willReturn(true);
        $unused = self::createMock(RateLimitConditionInterface::class);
        $unused->expects(self::never())->method('matches');

        self::assertTrue($this->evaluator([
            MatchingCondition::class => $true,
            UnusedCondition::class => $unused,
        ])->matches(new AnyOf([
            MatchingCondition::class,
            UnusedCondition::class,
        ])));
    }

    #[Test]
    public function itRejectsUnsupportedConditionExpressions(): void
    {
        $condition = new class implements RateLimitCondition {
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unsupported rate limit condition expression');

        $this->evaluator([])->matches($condition);
    }

    /**
     * @param array<string, RateLimitConditionInterface> $conditions
     */
    private function evaluator(array $conditions): RateLimitConditionEvaluator
    {
        return new RateLimitConditionEvaluator(new RateLimitStrategyRegistry(
            identityResolvers: [],
            conditions: $conditions,
        ));
    }
}
