<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Metadata;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AllOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AnyOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Not;
use JacyImp\ApiPlatformRateLimiter\Tests\Unit\Metadata\Fixture\MetadataCondition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AllOf::class)]
#[CoversClass(AnyOf::class)]
#[CoversClass(Not::class)]
final class ConditionTest extends TestCase
{
    #[Test]
    public function itRejectsAnEmptyAllOf(): void
    {
        $this->expectException(InvalidRateLimitException::class);

        new AllOf([]);
    }

    #[Test]
    public function itRejectsAnEmptyAnyOf(): void
    {
        $this->expectException(InvalidRateLimitException::class);

        new AnyOf([]);
    }

    #[Test]
    public function itAcceptsClassStringChildrenAndNegation(): void
    {
        $condition = MetadataCondition::class;
        $not = new Not($condition);
        $allOf = new AllOf([$condition]);
        $anyOf = new AnyOf([$condition, $not]);

        self::assertSame([$condition], $allOf->conditions);
        self::assertSame([$condition, $not], $anyOf->conditions);
        self::assertSame($condition, $not->condition);
    }
}
