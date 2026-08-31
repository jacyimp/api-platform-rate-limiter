<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\ApiPlatform\Fixture;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\SharedRateLimit;

#[ApiResource(
    operations: [
        new Get(
            name: 'operation_limited_get',
            extraProperties: [
                RateLimit::class => new RateLimit(limit: 10, interval: '1 second'),
                SharedRateLimit::class => new SharedRateLimit('operation'),
            ],
        ),
    ],
    extraProperties: [
        RateLimit::class => new RateLimit(limit: 100, interval: '1 minute'),
        SharedRateLimit::class => new SharedRateLimit('resource'),
    ],
)]
final class OperationLimitedResource
{
}
