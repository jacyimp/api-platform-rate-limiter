<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Laravel;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;

/** @internal */
final readonly class LaravelIdentityResolver implements IdentityResolverInterface
{
    public function __construct(private Request $request)
    {
    }

    public function resolve(): ?string
    {
        $user = $this->request->user();

        if ($user instanceof Authenticatable) {
            $identifier = $user->getAuthIdentifier();
            if (
                (is_int($identifier) || is_string($identifier))
                && trim((string) $identifier) !== ''
            ) {
                return sprintf('user:%s', $identifier);
            }
        }

        $ip = $this->request->ip();

        return is_string($ip) && trim($ip) !== ''
            ? sprintf('ip:%s', $ip)
            : null;
    }
}
