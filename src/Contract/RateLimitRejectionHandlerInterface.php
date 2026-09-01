<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

/**
 * Converts a rejected rate limit into the application's error response.
 *
 * For example, throw the framework's response exception with a 429 status.
 */
interface RateLimitRejectionHandlerInterface
{
    /** Handle the rejection and terminate the current request flow. */
    public function reject(
        RateLimitRejection $rejection,
    ): never;
}
