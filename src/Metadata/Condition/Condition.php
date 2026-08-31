<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata\Condition;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;

final readonly class Condition implements RateLimitCondition
{
    /** @param string $service Symfony service ID or condition class-string */
    public function __construct(public string $service)
    {
        if (trim($service) === '') {
            throw new InvalidRateLimitException(
                'Rate limit condition service ID cannot be empty.',
            );
        }
    }
}
