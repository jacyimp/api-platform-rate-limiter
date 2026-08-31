<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Symfony\Fixture;

use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;

final class NeverApplyCondition implements RateLimitConditionInterface
{
    public function shouldApply(): bool
    {
        return false;
    }
}
