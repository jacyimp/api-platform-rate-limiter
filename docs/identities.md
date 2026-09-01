# Choosing who gets rate limited

Every counter belongs to an identity. By default the package uses the authenticated user identifier, then falls back to the client IP.

Symfony uses `Request::getClientIp()` and Laravel uses `Request::ip()`. Configure each framework's trusted proxies; do not parse `X-Forwarded-For` in a resolver.

## Limit by IP

The default identity already limits guests by IP. Use an explicit resolver when authenticated users must also be grouped by IP.

Symfony:

```php
<?php

namespace App\RateLimit;

use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class IpIdentityResolver implements IdentityResolverInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function resolve(): ?string
    {
        $ip = $this->requestStack->getCurrentRequest()?->getClientIp();

        return $ip === null ? null : 'ip:' . $ip;
    }
}
```

Laravel:

```php
<?php

namespace App\RateLimit;

use Illuminate\Http\Request;
use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;

final readonly class IpIdentityResolver implements IdentityResolverInterface
{
    public function __construct(
        private Request $request,
    ) {
    }

    public function resolve(): ?string
    {
        $ip = $this->request->ip();

        return is_string($ip) && $ip !== '' ? 'ip:' . $ip : null;
    }
}
```

Reference the resolver from a login operation:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\RateLimit\IpIdentityResolver;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\Identity;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/login',
            extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 5,
                    interval: '1 minute',
                    identity: new Identity(IpIdentityResolver::class),
                ),
            ],
        ),
    ],
)]
final class LoginAttempt
{
    // ...
}
```

Symfony autoconfigures the implementation. On Laravel, register it in the published config:

```php
'resolvers' => [
    // ...
    'identity' => [
        App\RateLimit\IpIdentityResolver::class,
    ],
],
```

## Limit by authenticated user

On an authenticated operation, the default resolver is already per user. An explicit resolver is useful inside fallback or composite identities.

Symfony:

```php
<?php

namespace App\RateLimit;

use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class UserIdentityResolver implements IdentityResolverInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function resolve(): ?string
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        return $user instanceof UserInterface
            ? 'user:' . $user->getUserIdentifier()
            : null;
    }
}
```

Laravel:

```php
<?php

namespace App\RateLimit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;

final readonly class UserIdentityResolver implements IdentityResolverInterface
{
    public function __construct(
        private Request $request,
    ) {
    }

    public function resolve(): ?string
    {
        $user = $this->request->user();
        $id = $user instanceof Authenticatable
            ? $user->getAuthIdentifier()
            : null;

        return is_int($id) || (is_string($id) && $id !== '')
            ? 'user:' . $id
            : null;
    }
}
```

Use this only on operations that require authentication, or place it in `FirstAvailableIdentity` so guests have a fallback:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\RateLimit\UserIdentityResolver;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\Identity;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 500,
                    interval: '1 minute',
                    identity: new Identity(UserIdentityResolver::class),
                ),
            ],
        ),
    ],
)]
final class Invoice
{
    // ...
}
```

The `security` expression is the Symfony API Platform form. On Laravel, protect the operation with the authentication middleware used by your application.

## Limit by API key

Return `null` when the key is absent so the resolver can participate in a fallback. Hashing avoids carrying the raw credential into counter identities and lifecycle events.

Symfony:

```php
<?php

namespace App\RateLimit;

use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class ApiKeyIdentityResolver implements IdentityResolverInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function resolve(): ?string
    {
        $apiKey = $this->requestStack
            ->getCurrentRequest()
            ?->headers
            ->get('X-Api-Key');

        return is_string($apiKey) && $apiKey !== ''
            ? 'api-key:' . hash('sha256', $apiKey)
            : null;
    }
}
```

Laravel:

```php
<?php

namespace App\RateLimit;

use Illuminate\Http\Request;
use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;

final readonly class ApiKeyIdentityResolver implements IdentityResolverInterface
{
    public function __construct(
        private Request $request,
    ) {
    }

    public function resolve(): ?string
    {
        $apiKey = $this->request->header('X-Api-Key');

        return is_string($apiKey) && $apiKey !== ''
            ? 'api-key:' . hash('sha256', $apiKey)
            : null;
    }
}
```

Use it on the operation:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\RateLimit\ApiKeyIdentityResolver;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\Identity;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new GetCollection(
            extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 100,
                    interval: '1 minute',
                    identity: new Identity(ApiKeyIdentityResolver::class),
                ),
            ],
        ),
    ],
)]
final class Product
{
    // ...
}
```

If the endpoint permits requests without a key, use a fallback identity instead of this resolver alone.

## Fallback identities

`FirstAvailableIdentity` tries resolvers in order and uses the first non-null result. The resolver implementations below were defined in the preceding sections.

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\RateLimit\ApiKeyIdentityResolver;
use App\RateLimit\IpIdentityResolver;
use App\RateLimit\UserIdentityResolver;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\FirstAvailableIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\Identity;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new GetCollection(
            extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 100,
                    interval: '1 minute',
                    identity: new FirstAvailableIdentity([
                        new Identity(ApiKeyIdentityResolver::class),
                        new Identity(UserIdentityResolver::class),
                        new Identity(IpIdentityResolver::class),
                    ]),
                ),
            ],
        ),
    ],
)]
final class Product
{
    // ...
}
```

An unresolved or empty final identity rejects enforcement with an identity-resolution exception; it does not create an anonymous shared counter.

## Composite identities

`CompositeIdentity` combines every component. If any resolver returns `null`, the composite cannot be resolved.

For tenant-aware applications, resolve the tenant from a request attribute populated by trusted authentication or tenant middleware.

Symfony:

```php
<?php

namespace App\RateLimit;

use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class TenantIdentityResolver implements IdentityResolverInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function resolve(): ?string
    {
        $tenantId = $this->requestStack
            ->getCurrentRequest()
            ?->attributes
            ->get('tenant_id');

        return is_string($tenantId) && $tenantId !== ''
            ? 'tenant:' . $tenantId
            : null;
    }
}
```

Laravel:

```php
<?php

namespace App\RateLimit;

use Illuminate\Http\Request;
use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;

final readonly class TenantIdentityResolver implements IdentityResolverInterface
{
    public function __construct(
        private Request $request,
    ) {
    }

    public function resolve(): ?string
    {
        $tenantId = $this->request->attributes->get('tenant_id');

        return is_string($tenantId) && $tenantId !== ''
            ? 'tenant:' . $tenantId
            : null;
    }
}
```

Combine tenant and user so each user receives a separate allowance within a tenant:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\RateLimit\TenantIdentityResolver;
use App\RateLimit\UserIdentityResolver;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\CompositeIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\Identity;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new GetCollection(
            extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 1000,
                    interval: '1 minute',
                    identity: new CompositeIdentity([
                        new Identity(TenantIdentityResolver::class),
                        new Identity(UserIdentityResolver::class),
                    ]),
                ),
            ],
        ),
    ],
)]
final class Product
{
    // ...
}
```

Use only `TenantIdentityResolver` when every user in the tenant should spend from one shared tenant allowance.

## Custom identity on a global limit

The same resolver expressions work in central configuration.

Symfony:

```yaml
# config/packages/api_platform_rate_limiter.yaml

api_platform_rate_limiter:
    globals:
        api:
            limit: 1000
            interval: '1 minute'
            identity:
                composite:
                    - App\RateLimit\TenantIdentityResolver
                    - App\RateLimit\UserIdentityResolver
```

Laravel:

```php
<?php

// config/api-platform-rate-limiter.php

return [
    // ...
    'globals' => [
        'api' => [
            'limit' => 1000,
            'interval' => '1 minute',
            'identity' => [
                'composite' => [
                    App\RateLimit\TenantIdentityResolver::class,
                    App\RateLimit\UserIdentityResolver::class,
                ],
            ],
        ],
    ],
    'resolvers' => [
        // ...
        'identity' => [
            App\RateLimit\TenantIdentityResolver::class,
            App\RateLimit\UserIdentityResolver::class,
        ],
    ],
];
```

Symfony autoconfigures all `IdentityResolverInterface` services. Laravel requires every selectable identity resolver in `resolvers.identity`.

## See also

- [Quotas and shared limits](quotas.md)
- [Plans, tenants, and dynamic quotas](plans-and-tenants.md)
- [Conditional limits and bypasses](conditions-and-bypasses.md)
- [README](../README.md)
