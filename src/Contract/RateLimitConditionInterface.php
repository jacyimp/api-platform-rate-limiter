<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

interface RateLimitConditionInterface
{
    public function matches(): bool;
}
