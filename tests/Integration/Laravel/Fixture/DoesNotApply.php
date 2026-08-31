<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture;

use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;

final class DoesNotApply implements RateLimitConditionInterface
{
    public function matches(): bool
    {
        return false;
    }
}
