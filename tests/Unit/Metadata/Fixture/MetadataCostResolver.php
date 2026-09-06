<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Metadata\Fixture;

use JacyImp\ApiPlatformRateLimiter\Contract\CostResolverInterface;

final class MetadataCostResolver implements CostResolverInterface
{
    public function resolve(): int
    {
        return 1;
    }
}
