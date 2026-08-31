<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture;

use JacyImp\ApiPlatformRateLimiter\Contract\CostResolverInterface;

final class FixedCost implements CostResolverInterface
{
    public function resolve(): int
    {
        return 2;
    }
}
