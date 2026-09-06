# Plans, tenants, and dynamic quotas

Resolve limits, bucket names, and request cost from the current application context when a static declaration is not enough.

The examples read trusted request attributes populated by your authentication, billing, or tenancy middleware. Do not take plan or tenant values directly from untrusted client input.

Bucket and identity have separate roles. A bucket selects the quota namespace or configured definition; identity decides who shares and consumes its counter. The effective counter includes bucket, identity, policy, limit, and interval. Unless metadata supplies a custom identity, the identity is the authenticated user and falls back to the client IP.

## Different quotas by subscription plan

Implement `LimitResolverInterface` to return the current plan's allowance.

Symfony:

```php
<?php

namespace App\RateLimit;

use JacyImp\ApiPlatformRateLimiter\Contract\LimitResolverInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class PlanLimitResolver implements LimitResolverInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function resolve(): int
    {
        $plan = $this->requestStack
            ->getCurrentRequest()
            ?->attributes
            ->get('subscription_plan', 'free');

        return match ($plan) {
            'premium' => 1000,
            'enterprise' => 10_000,
            default => 100,
        };
    }
}
```

Laravel:

```php
<?php

namespace App\RateLimit;

use Illuminate\Http\Request;
use JacyImp\ApiPlatformRateLimiter\Contract\LimitResolverInterface;

final readonly class PlanLimitResolver implements LimitResolverInterface
{
    public function __construct(
        private Request $request,
    ) {
    }

    public function resolve(): int
    {
        $plan = $this->request->attributes->get(
            'subscription_plan',
            'free',
        );

        return match ($plan) {
            'premium' => 1000,
            'enterprise' => 10_000,
            default => 100,
        };
    }
}
```

Reference it with its class name:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\RateLimit\PlanLimitResolver;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new GetCollection(
            extraProperties: [
                new RateLimit(
                    limit: PlanLimitResolver::class,
                    interval: '1 minute',
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

This gives free accounts 100 requests/minute, premium accounts 1,000 requests/minute, and enterprise accounts 10,000 requests/minute.

The same dynamic limit can apply globally.

Symfony:

```yaml
# config/packages/api_platform_rate_limiter.yaml

api_platform_rate_limiter:
    globals:
        api:
            limit:
                resolver: App\RateLimit\PlanLimitResolver
            interval: '1 minute'
```

Laravel:

```php
'globals' => [
    'api' => [
        'limit' => [
            'resolver' => App\RateLimit\PlanLimitResolver::class,
        ],
        'interval' => '1 minute',
    ],
],
```

Because the limit is part of counter identity, changing the resolved limit selects a new counter.

## Share a quota by tenant

Use an identity resolver when every user in a tenant should consume the same counter.

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

    public function resolve(): string
    {
        $tenantId = $this->requestStack
            ->getCurrentRequest()
            ?->attributes
            ->get('tenant_id');

        if (!is_string($tenantId) || $tenantId === '') {
            throw new \RuntimeException('A tenant is required.');
        }

        return 'tenant:' . $tenantId;
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

    public function resolve(): string
    {
        $tenantId = $this->request->attributes->get('tenant_id');

        if (!is_string($tenantId) || $tenantId === '') {
            throw new \RuntimeException('A tenant is required.');
        }

        return 'tenant:' . $tenantId;
    }
}
```

Use a stable bucket and the same tenant identity on every operation that should share the quota:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\RateLimit\TenantIdentityResolver;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new GetCollection(
            extraProperties: [
                new RateLimit(
                    bucket: 'catalog',
                    limit: 1000,
                    interval: '1 minute',
                    identity: TenantIdentityResolver::class,
                ),
            ],
        ),
        new Get(
            extraProperties: [
                new RateLimit(
                    bucket: 'catalog',
                    limit: 1000,
                    interval: '1 minute',
                    identity: TenantIdentityResolver::class,
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

All users and both operations in tenant `123` consume `catalog + tenant:123`; tenant `456` consumes a separate `catalog + tenant:456` counter.

## Per-user quotas within a tenant

Combine tenant and user identities when each user should have a separate counter inside the tenant namespace:

```php
<?php

use App\RateLimit\TenantIdentityResolver;
use App\RateLimit\UserIdentityResolver;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\CompositeIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

new RateLimit(
    bucket: 'catalog',
    limit: 1000,
    interval: '1 minute',
    identity: new CompositeIdentity([
        TenantIdentityResolver::class,
        UserIdentityResolver::class,
    ]),
);
```

This produces distinct counters such as `catalog + tenant:123/user:A` and `catalog + tenant:123/user:B`.

## Select a configured bucket dynamically

A dynamic bucket can select a centrally configured definition. First define the plan quotas.

Symfony:

```yaml
# config/packages/api_platform_rate_limiter.yaml

api_platform_rate_limiter:
    buckets:
        free:
            limit: 100
            interval: '1 minute'

        premium:
            limit: 1000
            interval: '1 minute'

        enterprise:
            limit: 10000
            interval: '1 minute'
```

Laravel:

```php
'buckets' => [
    'free' => [
        'limit' => 100,
        'interval' => '1 minute',
    ],
    'premium' => [
        'limit' => 1000,
        'interval' => '1 minute',
    ],
    'enterprise' => [
        'limit' => 10_000,
        'interval' => '1 minute',
    ],
],
```

Return one of those configured names:

```php
<?php

namespace App\RateLimit;

use JacyImp\ApiPlatformRateLimiter\Contract\BucketResolverInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class PlanBucketResolver implements BucketResolverInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function resolve(): string
    {
        $plan = $this->requestStack
            ->getCurrentRequest()
            ?->attributes
            ->get('subscription_plan', 'free');

        return match ($plan) {
            'premium' => 'premium',
            'enterprise' => 'enterprise',
            default => 'free',
        };
    }
}
```

Laravel:

```php
<?php

namespace App\RateLimit;

use Illuminate\Http\Request;
use JacyImp\ApiPlatformRateLimiter\Contract\BucketResolverInterface;

final readonly class PlanBucketResolver implements BucketResolverInterface
{
    public function __construct(
        private Request $request,
    ) {
    }

    public function resolve(): string
    {
        $plan = $this->request->attributes->get(
            'subscription_plan',
            'free',
        );

        return match ($plan) {
            'premium' => 'premium',
            'enterprise' => 'enterprise',
            default => 'free',
        };
    }
}
```

Reference the dynamic bucket without `limit` or `interval`; the resolved configured bucket supplies both:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\RateLimit\PlanBucketResolver;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new GetCollection(
            extraProperties: [
                new RateLimit(
                    bucketResolver: PlanBucketResolver::class,
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

`bucketResolver` answers: “Which bucket/quota definition should this request use?” It does not decide who shares the counter. Identity does that. With the default identity, users A and B remain separate even when both resolve to the same plan bucket.

Use this approach when plan definitions should live in configuration. Use a limit resolver class name when the calculation belongs in application code or does not map cleanly to named plans.

## Dynamic request cost

Implement `CostResolverInterface` when the request decides how many tokens to consume.

Symfony:

```php
<?php

namespace App\RateLimit;

use JacyImp\ApiPlatformRateLimiter\Contract\CostResolverInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class SearchCostResolver implements CostResolverInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function resolve(): int
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request?->query->getBoolean('includeDetails') ? 5 : 1;
    }
}
```

Laravel:

```php
<?php

namespace App\RateLimit;

use Illuminate\Http\Request;
use JacyImp\ApiPlatformRateLimiter\Contract\CostResolverInterface;

final readonly class SearchCostResolver implements CostResolverInterface
{
    public function __construct(
        private Request $request,
    ) {
    }

    public function resolve(): int
    {
        return $this->request->boolean('includeDetails') ? 5 : 1;
    }
}
```

Use the cost resolver class name in the API Platform operation:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\RateLimit\SearchCostResolver;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/products/search',
            extraProperties: [
                new RateLimit(
                    bucket: 'catalog-search',
                    limit: 1000,
                    interval: '1 minute',
                    cost: SearchCostResolver::class,
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

Every resolver must return a positive integer. Cost changes token consumption, not counter identity.

## Framework registration

Symfony autoconfigures implementations of `LimitResolverInterface`, `BucketResolverInterface`, and `CostResolverInterface`.

Laravel requires selectable resolvers in the published config:

```php
'resolvers' => [
    // ...
    'identity' => [
        App\RateLimit\TenantIdentityResolver::class,
        App\RateLimit\UserIdentityResolver::class,
    ],
    'bucket' => [
        App\RateLimit\PlanBucketResolver::class,
    ],
    'limit' => [
        App\RateLimit\PlanLimitResolver::class,
    ],
    'cost' => [
        App\RateLimit\SearchCostResolver::class,
    ],
],
```

## See also

- [Choosing who gets rate limited](identities.md)
- [Quotas and shared limits](quotas.md)
- [Extending the rate limiter](extending.md)
- [README](../README.md)
