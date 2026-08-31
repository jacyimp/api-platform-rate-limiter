<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core\Fixture;

use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;

final readonly class FixedNullableIdentityResolver implements IdentityResolverInterface
{
    public function __construct(private ?string $identity)
    {
    }

    public function resolve(): ?string
    {
        return $this->identity;
    }
}
