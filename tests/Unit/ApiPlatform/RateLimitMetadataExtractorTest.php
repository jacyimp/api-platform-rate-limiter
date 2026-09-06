<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\ApiPlatform;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Resource\Factory\AttributesResourceMetadataCollectionFactory;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitMetadataExtractor;
use JacyImp\ApiPlatformRateLimiter\Metadata\BypassRateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;
use JacyImp\ApiPlatformRateLimiter\Tests\Unit\ApiPlatform\Fixture\OperationBypassedResource;
use JacyImp\ApiPlatformRateLimiter\Tests\Unit\ApiPlatform\Fixture\OperationLimitedResource;
use JacyImp\ApiPlatformRateLimiter\Tests\Unit\ApiPlatform\Fixture\ResourceBypassedResource;
use JacyImp\ApiPlatformRateLimiter\Tests\Unit\ApiPlatform\Fixture\ResourceLimitedResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitMetadataExtractor::class)]
final class RateLimitMetadataExtractorTest extends TestCase
{
    private RateLimitMetadataExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new RateLimitMetadataExtractor();
    }

    #[Test]
    public function itExtractsOneRateLimit(): void
    {
        $rateLimit = new RateLimit(limit: 100, interval: '1 minute');

        self::assertSame([$rateLimit], $this->extractor->extract(new Get(
            extraProperties: [$rateLimit],
        )));
    }

    #[Test]
    public function itExtractsEveryRateLimitInOrder(): void
    {
        $first = new RateLimit(limit: 100, interval: '1 minute');
        $second = new RateLimit(bucket: 'catalog');

        self::assertSame([$first, $second], $this->extractor->extract(new Get(
            extraProperties: [$first, 'unrelated', $second],
        )));
    }

    #[Test]
    public function itExtractsRateLimitsDefinedOnTheResource(): void
    {
        $metadata = (new AttributesResourceMetadataCollectionFactory())
            ->create(ResourceLimitedResource::class);

        self::assertEquals([
            new RateLimit(limit: 100, interval: '1 minute'),
            new RateLimit(bucket: 'catalog'),
        ], $this->extractor->extract($metadata->getOperation('resource_limited_get')));
    }

    #[Test]
    public function itCombinesOperationAndResourceRateLimitsInMergedOrder(): void
    {
        $metadata = (new AttributesResourceMetadataCollectionFactory())
            ->create(OperationLimitedResource::class);

        self::assertEquals([
            new RateLimit(limit: 100, interval: '1 minute'),
            new RateLimit(bucket: 'resource'),
            new RateLimit(limit: 10, interval: '1 second'),
            new RateLimit(bucket: 'operation'),
        ], $this->extractor->extract($metadata->getOperation('operation_limited_get')));
    }

    #[Test]
    public function itReturnsEmptyListWhenOperationHasNoRateLimit(): void
    {
        self::assertSame([], $this->extractor->extract(new Get()));
    }

    #[Test]
    public function itExtractsOneBypass(): void
    {
        $bypass = new BypassRateLimit(bucket: 'catalog');

        self::assertSame([$bypass], $this->extractor->extractBypasses(new Get(
            extraProperties: [$bypass],
        )));
    }

    #[Test]
    public function itExtractsEveryBypassInOrder(): void
    {
        $first = new BypassRateLimit(bucket: 'catalog');
        $second = new BypassRateLimit(bucket: 'checkout');

        self::assertSame([$first, $second], $this->extractor->extractBypasses(new Get(
            extraProperties: [$first, $second],
        )));
    }

    #[Test]
    public function itExtractsMixedRateLimitsAndBypasses(): void
    {
        $first = new RateLimit(limit: 10, interval: '1 minute');
        $bypass = new BypassRateLimit(bucket: 'catalog');
        $second = new RateLimit(bucket: 'catalog');
        $operation = new Get(extraProperties: [$first, $bypass, $second]);

        self::assertSame([$first, $second], $this->extractor->extract($operation));
        self::assertSame([$bypass], $this->extractor->extractBypasses($operation));
    }

    #[Test]
    public function itExtractsBypassesDefinedOnTheResource(): void
    {
        $metadata = (new AttributesResourceMetadataCollectionFactory())
            ->create(ResourceBypassedResource::class);

        self::assertEquals(
            [new BypassRateLimit(bucket: 'resource')],
            $this->extractor->extractBypasses($metadata->getOperation('resource_bypassed_get')),
        );
    }

    #[Test]
    public function itCombinesOperationAndResourceBypasses(): void
    {
        $metadata = (new AttributesResourceMetadataCollectionFactory())
            ->create(OperationBypassedResource::class);

        self::assertEquals([
            new BypassRateLimit(bucket: 'resource'),
            new BypassRateLimit(bucket: 'operation'),
        ], $this->extractor->extractBypasses($metadata->getOperation('operation_bypassed_get')));
    }

    #[Test]
    public function itIgnoresOldClassKeyedMetadata(): void
    {
        $operation = new Get(extraProperties: [
            RateLimit::class => new RateLimit(limit: 10, interval: '1 minute'),
            BypassRateLimit::class => new BypassRateLimit(),
        ]);

        self::assertSame([], $this->extractor->extract($operation));
        self::assertSame([], $this->extractor->extractBypasses($operation));
    }
}
