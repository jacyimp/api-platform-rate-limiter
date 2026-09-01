<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata\Identity;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;

/**
 * References an identity resolver service for the rate-limit counter.
 *
 * Example: `new Identity(ApiKeyIdentityResolver::class)`.
 */
final readonly class Identity implements IdentityExpression
{
    /** @param string $resolver Symfony service ID or resolver class-string */
    public function __construct(public string $resolver)
    {
        if (trim($resolver) === '') {
            throw new InvalidRateLimitException(
                'Identity resolver service ID cannot be empty.',
            );
        }
    }
}
