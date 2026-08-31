<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

interface RateLimitRejectionHandlerInterface
{
    public function reject(
        RateLimitRejection $rejection,
    ): never;
}
