<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Tests\Unit\ApiPlatform;

use ApiPlatform\Metadata\Get;
use InvalidArgumentException;
use Jacyimp\ApiPlatformRateLimiter\ApiPlatform\RateLimitMetadataExtractor;
use Jacyimp\ApiPlatformRateLimiter\Metadata\OperationRateLimit;
use Jacyimp\ApiPlatformRateLimiter\Metadata\SharedRateLimit;
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
    public function itExtractsOperationRateLimit(): void
    {
        $rateLimit = new OperationRateLimit(
            limit: 100,
            interval: '1 minute',
        );

        $operation = new Get(
            extraProperties: [
                OperationRateLimit::class => $rateLimit,
            ],
        );

        self::assertSame(
            [$rateLimit],
            $this->extractor->extract($operation),
        );
    }

    #[Test]
    public function itExtractsSharedRateLimit(): void
    {
        $rateLimit = new SharedRateLimit('catalog');

        $operation = new Get(
            extraProperties: [
                SharedRateLimit::class => $rateLimit,
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
        $operationRateLimit = new OperationRateLimit(
            limit: 100,
            interval: '1 minute',
        );

        $sharedRateLimit = new SharedRateLimit('catalog');

        $operation = new Get(
            extraProperties: [
                OperationRateLimit::class => $operationRateLimit,
                SharedRateLimit::class => $sharedRateLimit,
            ],
        );

        self::assertSame(
            [$operationRateLimit, $sharedRateLimit],
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
    public function itRejectsInvalidOperationRateLimitMetadata(): void
    {
        $operation = new Get(
            extraProperties: [
                OperationRateLimit::class => 'invalid',
            ],
        );

        $this->expectException(InvalidArgumentException::class);

        $this->extractor->extract($operation);
    }

    #[Test]
    public function itRejectsInvalidSharedRateLimitMetadata(): void
    {
        $operation = new Get(
            extraProperties: [
                SharedRateLimit::class => 'invalid',
            ],
        );

        $this->expectException(InvalidArgumentException::class);

        $this->extractor->extract($operation);
    }
}
