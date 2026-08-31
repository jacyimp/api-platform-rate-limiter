<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Metadata;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AllOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AnyOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Condition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AllOf::class)]
#[CoversClass(AnyOf::class)]
#[CoversClass(Condition::class)]
final class ConditionTest extends TestCase
{
    #[Test]
    public function itRejectsAnEmptyServiceId(): void
    {
        $this->expectException(InvalidRateLimitException::class);

        new Condition(' ');
    }

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
    public function itRejectsMixedAllOfChildren(): void
    {
        $this->expectException(InvalidRateLimitException::class);

        new AllOf(['condition']);
    }
}
