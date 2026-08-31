<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Symfony\Fixture;

use JacyImp\ApiPlatformRateLimiter\Contract\CostResolverInterface;

final class FixedCostResolver implements CostResolverInterface
{
    public function resolve(): int
    {
        return 2;
    }
}
