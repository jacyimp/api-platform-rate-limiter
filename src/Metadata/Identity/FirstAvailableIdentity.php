<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata\Identity;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;

/**
 * Uses the first child identity that resolves to a non-null value.
 *
 * Example: `new FirstAvailableIdentity([User::class, Ip::class])`.
 */
final readonly class FirstAvailableIdentity implements IdentityExpression
{
    /** @var non-empty-list<class-string<\JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface>|IdentityExpression> */
    public array $identities;

    /** @param list<class-string<\JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface>|IdentityExpression> $identities */
    public function __construct(array $identities)
    {
        if ($identities === []) {
            throw new InvalidRateLimitException(
                'First-available identity requires at least one child expression.',
            );
        }

        $this->identities = $identities;
    }
}
