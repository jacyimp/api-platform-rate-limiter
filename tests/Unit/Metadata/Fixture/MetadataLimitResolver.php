<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Metadata\Fixture;

use JacyImp\ApiPlatformRateLimiter\Contract\LimitResolverInterface;

final class MetadataLimitResolver implements LimitResolverInterface
{
    public function resolve(): int
    {
        return 10;
    }
}
