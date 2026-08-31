<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture;

use JacyImp\ApiPlatformRateLimiter\Contract\LimitResolverInterface;

final class FixedLimit implements LimitResolverInterface
{
    public function resolve(): int
    {
        return 1;
    }
}
