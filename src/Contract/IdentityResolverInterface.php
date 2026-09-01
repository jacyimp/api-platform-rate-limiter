<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Contract;

/**
 * Resolves the identity used to separate rate-limit counters.
 *
 * Reference an implementation with `new Identity(ApiKeyIdentityResolver::class)`.
 */
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
