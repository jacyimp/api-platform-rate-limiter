<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

use Symfony\Component\HttpFoundation\Response;

/**
 * Converts a rejected rate limit into the application's error response.
 *
 * For example, return a framework response or throw a framework exception.
 */
interface RateLimitRejectionHandlerInterface
{
    /** Handle the rejection and terminate the current request flow. */
    public function reject(RateLimitRejection $rejection,): Response;
}
