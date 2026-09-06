<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core\Fixture;

use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;

final class MatchingCondition implements RateLimitConditionInterface
{
    public function matches(): bool
    {
        return true;
    }
}
