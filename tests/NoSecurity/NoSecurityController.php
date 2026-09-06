<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\NoSecurity;

use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class NoSecurityController
{
    public function __construct(
        private IdentityResolverInterface $identityResolver,
    ) {
    }

    public function __invoke(): Response
    {
        return new Response($this->identityResolver->resolve());
    }
}
