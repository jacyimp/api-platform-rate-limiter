<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Symfony\Fixture;

use JacyImp\ApiPlatformRateLimiter\Contract\DynamicCostResolverInterface;

final class FixedCostResolver implements DynamicCostResolverInterface
{
    public function resolve(): int
    {
        return 2;
    }
}
