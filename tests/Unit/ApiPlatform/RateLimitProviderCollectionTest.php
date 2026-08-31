<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\ApiPlatform;

use ApiPlatform\Metadata\Get;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitProviderCollection;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitProviderInterface;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitProviderCollection::class)]
final class RateLimitProviderCollectionTest extends TestCase
{
    #[Test]
    public function itReturnsEmptyListWithoutProviders(): void
    {
        $collection = new RateLimitProviderCollection([]);

        self::assertSame(
            [],
            $collection->provide(new Get()),
        );
    }

    #[Test]
    public function itCollectsRateLimitsFromProviders(): void
    {
        $operation = new Get();

        $operationRateLimit = new RateLimit(
            limit: 100,
            interval: '1 minute',
        );

        $sharedRateLimit = new RateLimit(bucket: 'catalog');
        $firstProvider = self::createMock(
            RateLimitProviderInterface::class,
        );

        $firstProvider
            ->expects(self::once())
            ->method('provide')
            ->with($operation)
            ->willReturn([
                $operationRateLimit,
            ]);

        $secondProvider = self::createMock(
            RateLimitProviderInterface::class,
        );

        $secondProvider
            ->expects(self::once())
            ->method('provide')
            ->with($operation)
            ->willReturn([
                $sharedRateLimit,
            ]);

        $collection = new RateLimitProviderCollection([
            $firstProvider,
            $secondProvider,
        ]);

        self::assertSame(
            [
                $operationRateLimit,
                $sharedRateLimit,
            ],
            $collection->provide($operation),
        );
    }

    #[Test]
    public function itPreservesProviderAndLimitOrdering(): void
    {
        $operation = new Get();

        $first = new RateLimit(
            limit: 10,
            interval: '1 minute',
        );

        $second = new RateLimit(
            limit: 20,
            interval: '1 minute',
        );

        $third = new RateLimit(bucket: 'catalog');
        $firstProvider = self::createStub(
            RateLimitProviderInterface::class,
        );

        $firstProvider
            ->method('provide')
            ->willReturn([
                $first,
                $second,
            ]);

        $secondProvider = self::createStub(
            RateLimitProviderInterface::class,
        );

        $secondProvider
            ->method('provide')
            ->willReturn([
                $third,
            ]);

        $collection = new RateLimitProviderCollection([
            $firstProvider,
            $secondProvider,
        ]);

        self::assertSame(
            [
                $first,
                $second,
                $third,
            ],
            $collection->provide($operation),
        );
    }
}
