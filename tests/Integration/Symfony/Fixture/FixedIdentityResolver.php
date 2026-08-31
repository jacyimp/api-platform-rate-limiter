<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Symfony\Fixture;

use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;

final class FixedIdentityResolver implements IdentityResolverInterface
{
    public function resolve(): string
    {
        return 'fixed:test';
    }
}
