<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Exception;

use Generator;
use InvalidArgumentException;
use JacyImp\ApiPlatformRateLimiter\Exception\IdentityResolutionException;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidIntervalException;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitMetadataException;
use JacyImp\ApiPlatformRateLimiter\Exception\RateLimiterExceptionInterface;
use JacyImp\ApiPlatformRateLimiter\Exception\RateLimitExceededException;
use JacyImp\ApiPlatformRateLimiter\Exception\UndefinedSharedBucketException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class ExceptionHierarchyTest extends TestCase
{
    /**
     * @param class-string<RateLimiterExceptionInterface> $exception
     * @param class-string<\Throwable> $parent
     */
    #[Test]
    #[DataProvider('exceptions')]
    public function itProvidesSpecificExceptionsWithCompatibleParents(
        string $exception,
        string $parent,
    ): void {
        self::assertTrue(is_subclass_of($exception, RateLimiterExceptionInterface::class));
        self::assertTrue(is_subclass_of($exception, $parent));
    }

    /**
     * @return Generator<string, array{class-string<RateLimiterExceptionInterface>, class-string<\Throwable>}>
     */
    public static function exceptions(): Generator
    {
        yield 'invalid rate limit' => [InvalidRateLimitException::class, InvalidArgumentException::class];
        yield 'invalid interval' => [InvalidIntervalException::class, InvalidArgumentException::class];
        yield 'invalid metadata' => [InvalidRateLimitMetadataException::class, InvalidArgumentException::class];
        yield 'undefined shared bucket' => [UndefinedSharedBucketException::class, InvalidArgumentException::class];
        yield 'identity resolution' => [IdentityResolutionException::class, RuntimeException::class];
        yield 'rate limit exceeded' => [RateLimitExceededException::class, TooManyRequestsHttpException::class];
    }
}
