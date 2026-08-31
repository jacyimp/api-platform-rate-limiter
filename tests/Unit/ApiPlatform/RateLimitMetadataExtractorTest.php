<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\ApiPlatform;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Resource\Factory\AttributesResourceMetadataCollectionFactory;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitMetadataExtractor;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitMetadataException;
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
    public function itExtractsRateLimit(): void
    {
        $rateLimit = new RateLimit(
            limit: 100,
            interval: '1 minute',
        );

        $operation = new Get(
            extraProperties: [
                RateLimit::class => $rateLimit,
            ],
        );

        self::assertSame(
            [$rateLimit],
            $this->extractor->extract($operation),
        );
    }

    #[Test]
    public function itExtractsBucketRateLimit(): void
    {
        $rateLimit = new RateLimit(bucket: 'catalog');
        $operation = new Get(
            extraProperties: [
                RateLimit::class => $rateLimit,
            ],
        );

        self::assertSame(
            [$rateLimit],
            $this->extractor->extract($operation),
        );
    }

    #[Test]
    public function itExtractsBothRateLimits(): void
    {
        $rateLimit = new RateLimit(
            limit: 100,
            interval: '1 minute',
        );

        $sharedRateLimit = new RateLimit(bucket: 'catalog');
        $operation = new Get(
            extraProperties: [
                RateLimit::class => [$rateLimit, $sharedRateLimit],
            ],
        );

        self::assertSame(
            [$rateLimit, $sharedRateLimit],
            $this->extractor->extract($operation),
        );
    }

    #[Test]
    public function itExtractsRateLimitsDefinedOnTheResource(): void
    {
        $metadata = (new AttributesResourceMetadataCollectionFactory())
            ->create(ResourceLimitedResource::class);
        $operation = $metadata->getOperation('resource_limited_get');

        self::assertEquals(
            [
                new RateLimit(limit: 100, interval: '1 minute'),
                new RateLimit(bucket: 'catalog'),
            ],
            $this->extractor->extract($operation),
        );
    }

    #[Test]
    public function itPrefersOperationRateLimitsOverResourceRateLimits(): void
    {
        $metadata = (new AttributesResourceMetadataCollectionFactory())
            ->create(OperationLimitedResource::class);
        $operation = $metadata->getOperation('operation_limited_get');

        self::assertEquals(
            [
                new RateLimit(limit: 10, interval: '1 second'),
                new RateLimit(bucket: 'operation'),
            ],
            $this->extractor->extract($operation),
        );
    }

    #[Test]
    public function itReturnsEmptyListWhenOperationHasNoRateLimit(): void
    {
        self::assertSame(
            [],
            $this->extractor->extract(new Get()),
        );
    }

    #[Test]
    public function itExtractsBypassesDefinedOnTheResource(): void
    {
        $metadata = (new AttributesResourceMetadataCollectionFactory())
            ->create(ResourceBypassedResource::class);

        self::assertEquals(
            [new BypassRateLimit(bucket: 'resource')],
            $this->extractor->extractBypasses(
                $metadata->getOperation('resource_bypassed_get'),
            ),
        );
    }

    #[Test]
    public function itPrefersOperationBypassesOverResourceBypasses(): void
    {
        $metadata = (new AttributesResourceMetadataCollectionFactory())
            ->create(OperationBypassedResource::class);

        self::assertEquals(
            [new BypassRateLimit(bucket: 'operation')],
            $this->extractor->extractBypasses(
                $metadata->getOperation('operation_bypassed_get'),
            ),
        );
    }

    #[Test]
    public function itRejectsInvalidBypassMetadata(): void
    {
        $operation = new Get(extraProperties: [
            BypassRateLimit::class => 'invalid',
        ]);

        $this->expectException(InvalidRateLimitMetadataException::class);

        $this->extractor->extractBypasses($operation);
    }

    #[Test]
    public function itRejectsInvalidRateLimitMetadata(): void
    {
        $operation = new Get(
            extraProperties: [
                RateLimit::class => 'invalid',
            ],
        );

        $this->expectException(InvalidRateLimitMetadataException::class);

        $this->extractor->extract($operation);
    }

    #[Test]
    public function itRejectsInvalidRateLimitList(): void
    {
        $operation = new Get(
            extraProperties: [
                RateLimit::class => [
                    new RateLimit(limit: 1, interval: '1 minute'),
                    'invalid',
                ],
            ],
        );

        $this->expectException(InvalidRateLimitMetadataException::class);

        $this->extractor->extract($operation);
    }
}
