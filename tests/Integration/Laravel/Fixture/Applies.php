<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture;

use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;

final class Applies implements RateLimitConditionInterface
{
    public function matches(): bool
    {
        return true;
    }
}
