<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

interface IdentityResolverInterface
{
    /**
     * Return the current identity, or null when this resolver cannot provide one.
     *
     * A null default identity causes enforcement to fail. In a
     * FirstAvailableIdentity expression, null selects the next resolver.
     */
    public function resolve(): ?string;
}
