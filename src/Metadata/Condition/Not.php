<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata\Condition;

final readonly class Not implements RateLimitCondition
{
    public function __construct(public RateLimitCondition $condition)
    {
    }
}
