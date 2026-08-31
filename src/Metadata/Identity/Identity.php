<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata\Identity;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;

final readonly class Identity implements IdentityExpression
{
    public function __construct(public string $resolver)
    {
        if (trim($resolver) === '') {
            throw new InvalidRateLimitException(
                'Identity resolver service ID cannot be empty.',
            );
        }
    }
}
