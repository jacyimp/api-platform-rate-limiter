<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture;

use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitBypassInterface;

final class AlwaysBypass implements RateLimitBypassInterface
{
    public function shouldBypass(): bool
    {
        return true;
    }
}
