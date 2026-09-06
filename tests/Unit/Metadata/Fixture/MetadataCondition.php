<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Metadata\Fixture;

use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;

final class MetadataCondition implements RateLimitConditionInterface
{
    public function matches(): bool
    {
        return true;
    }
}
