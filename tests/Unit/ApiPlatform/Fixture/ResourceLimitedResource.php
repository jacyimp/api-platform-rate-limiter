<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\ApiPlatform\Fixture;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\SharedRateLimit;

#[ApiResource(
    operations: [new Get(name: 'resource_limited_get')],
    extraProperties: [
        RateLimit::class => new RateLimit(limit: 100, interval: '1 minute'),
        SharedRateLimit::class => new SharedRateLimit('catalog'),
    ],
)]
final class ResourceLimitedResource
{
}
