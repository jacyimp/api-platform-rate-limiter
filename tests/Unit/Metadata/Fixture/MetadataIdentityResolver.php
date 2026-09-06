<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Metadata\Fixture;

use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;

final class MetadataIdentityResolver implements IdentityResolverInterface
{
    public function __construct(private ?string $identity = 'identity')
    {
    }

    public function resolve(): ?string
    {
        return $this->identity;
    }
}
