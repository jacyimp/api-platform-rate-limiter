<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\ApiPlatform;

use ApiPlatform\Metadata\Get;
use DateInterval;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitDescription;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitMetadataExtractor;
use JacyImp\ApiPlatformRateLimiter\Core\IntervalNormalizer;
use JacyImp\ApiPlatformRateLimiter\Core\SharedRateLimitRegistry;
use JacyImp\ApiPlatformRateLimiter\Metadata\BypassRateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;
use JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture\DoesNotApply;
use JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture\FixedBucket;
use JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture\FixedLimit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitDescription::class)]
final class RateLimitDescriptionTest extends TestCase
{
    #[Test]
    public function itDescribesInlineSharedAndGlobalQuotas(): void
    {
        $description = new RateLimitDescription(
            new RateLimitMetadataExtractor(),
            new SharedRateLimitRegistry(['shared' => new RateLimit(50, new DateInterval('PT1H'), cost: 2)]),
            new IntervalNormalizer(),
            ['api' => new RateLimit(1000, '1 day')],
        );
        $text = $description->describe(new Get(extraProperties: [
            new RateLimit(100, '1 minute'),
            new RateLimit(bucket: 'shared', cost: 3),
        ]), 'get');

        self::assertStringContainsString('100 tokens per 1 minute (sliding_window)', $text);
        self::assertStringContainsString('50 tokens per 3600 seconds', $text);
        self::assertStringContainsString('Cost: 6 token(s) per request. Shared bucket.', $text);
        self::assertStringContainsString('Global quota: 1000 tokens per 1 day', $text);
        self::assertStringContainsString('HTTP 429', $text);
    }

    #[Test]
    public function itDescribesRuntimeValuesWithoutInvokingResolversOrConditions(): void
    {
        $description = $this->description();
        $text = $description->describe(new Get(extraProperties: [
            new RateLimit(FixedLimit::class, '1 minute', when: DoesNotApply::class),
            new RateLimit(bucketResolver: FixedBucket::class),
        ]), 'get');

        self::assertStringContainsString('Dynamic tokens per 1 minute', $text);
        self::assertStringContainsString('Applies conditionally.', $text);
        self::assertStringContainsString('Quota and interval determined at request time.', $text);
        self::assertStringContainsString('Request cost is determined at request time.', $text);
    }

    #[Test]
    public function itOmitsUnlimitedAndUnconditionallyBypassedOperations(): void
    {
        self::assertSame('', $this->description()->describe(new Get(), 'get'));
        self::assertSame('', $this->description()->describe(new Get(extraProperties: [
            new RateLimit(10, '1 minute'), new BypassRateLimit(),
        ]), 'get'));
    }

    #[Test]
    public function itHonorsNamedBypassesAndMarksConditionalBypasses(): void
    {
        $text = $this->description()->describe(new Get(extraProperties: [
            new RateLimit(10, '1 minute', bucket: 'shared'),
            new RateLimit(20, '1 minute'),
            new BypassRateLimit(bucket: 'shared'),
            new BypassRateLimit(bucket: 'operation:get', when: DoesNotApply::class),
        ]), 'get');

        self::assertStringNotContainsString('10 tokens', $text);
        self::assertStringContainsString('20 tokens', $text);
        self::assertStringContainsString('Applies conditionally.', $text);
    }

    private function description(): RateLimitDescription
    {
        return new RateLimitDescription(
            new RateLimitMetadataExtractor(),
            new SharedRateLimitRegistry([]),
            new IntervalNormalizer(),
        );
    }
}
