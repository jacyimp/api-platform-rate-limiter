<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture;

use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;

final class PrimaryIdentity implements IdentityResolverInterface
{
    public function resolve(): string
    {
        return 'primary';
    }
}
