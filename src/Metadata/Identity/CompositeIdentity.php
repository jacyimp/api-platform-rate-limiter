<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata\Identity;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;

final readonly class CompositeIdentity implements IdentityExpression
{
    /** @var non-empty-list<IdentityExpression> */
    public array $identities;

    /** @param list<IdentityExpression> $identities */
    public function __construct(array $identities)
    {
        if ($identities === []) {
            throw new InvalidRateLimitException(
                'Composite identity requires at least one child expression.',
            );
        }

        $this->identities = $identities;
    }
}
