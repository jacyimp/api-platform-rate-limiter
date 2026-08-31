<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Metadata;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;
use JacyImp\ApiPlatformRateLimiter\Metadata\BypassRateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Condition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BypassRateLimit::class)]
final class BypassRateLimitTest extends TestCase
{
    #[Test]
    public function itAcceptsOptionalBucketAndCondition(): void
    {
        $condition = new Condition('condition');
        $bypass = new BypassRateLimit(bucket: 'catalog', when: $condition);

        self::assertSame('catalog', $bypass->bucket);
        self::assertSame($condition, $bypass->when);
    }

    #[Test]
    public function itRejectsEmptyBucket(): void
    {
        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage('Bypass rate limit bucket cannot be empty.');

        new BypassRateLimit(bucket: ' ');
    }

    #[Test]
    public function itRejectsEmptyConditionServiceId(): void
    {
        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(
            'Rate limit condition service ID cannot be empty.',
        );

        new BypassRateLimit(when: new Condition(' '));
    }
}
