<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\ApiPlatform\Fixture;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use JacyImp\ApiPlatformRateLimiter\Metadata\BypassRateLimit;

#[ApiResource(
    operations: [
        new Get(
            name: 'operation_bypassed_get',
            extraProperties: [
                BypassRateLimit::class => new BypassRateLimit(bucket: 'operation'),
            ],
        ),
    ],
    extraProperties: [
        BypassRateLimit::class => new BypassRateLimit(bucket: 'resource'),
    ],
)]
final class OperationBypassedResource
{
}
