<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Metadata;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;
use JacyImp\ApiPlatformRateLimiter\Metadata\SharedRateLimit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SharedRateLimit::class)]
final class SharedRateLimitTest extends TestCase
{
    #[Test]
    public function itStoresBucketName(): void
    {
        $rateLimit = new SharedRateLimit('catalog');

        self::assertSame('catalog', $rateLimit->bucket);
    }

    #[Test]
    public function itStoresStrategies(): void
    {
        $rateLimit = new SharedRateLimit(
            bucket: 'catalog',
            identityResolver: 'app.identity_resolver',
            when: 'app.condition',
        );

        self::assertSame('app.identity_resolver', $rateLimit->identityResolver);
        self::assertSame('app.condition', $rateLimit->when);
    }

    #[Test]
    public function itRejectsEmptyBucket(): void
    {
        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(
            'Shared rate limit bucket cannot be empty.',
        );

        new SharedRateLimit('');
    }

    #[Test]
    public function itRejectsWhitespaceOnlyBucket(): void
    {
        $this->expectException(InvalidRateLimitException::class);

        new SharedRateLimit('   ');
    }

    #[Test]
    public function itRejectsEmptyIdentityResolverServiceId(): void
    {
        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(
            'Identity resolver service ID cannot be empty.',
        );

        new SharedRateLimit(
            bucket: 'catalog',
            identityResolver: ' ',
        );
    }

    #[Test]
    public function itRejectsEmptyConditionServiceId(): void
    {
        $this->expectException(InvalidRateLimitException::class);
        $this->expectExceptionMessage(
            'Rate limit condition service ID cannot be empty.',
        );

        new SharedRateLimit(
            bucket: 'catalog',
            when: '',
        );
    }
}
