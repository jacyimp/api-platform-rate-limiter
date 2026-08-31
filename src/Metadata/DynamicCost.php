<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;

final readonly class DynamicCost
{
    public function __construct(public string $resolver)
    {
        if (trim($resolver) === '') {
            throw new InvalidRateLimitException(
                'Cost resolver service ID cannot be empty.',
            );
        }
    }
}
