# Plans, tenants, and dynamic quotas

Resolve limits, bucket names, and request cost from the current application context when a static declaration is not enough.

The examples read trusted request attributes populated by your authentication, billing, or tenancy middleware. Do not take plan or tenant values directly from untrusted client input.

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

Reference it with `DynamicLimit`:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\RateLimit\PlanLimitResolver;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new GetCollection(
            extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: new DynamicLimit(PlanLimitResolver::class),
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

## Give each tenant a runtime bucket

Use `DynamicBucket` when the bucket name itself depends on the current tenant.

Symfony:

```php
<?php

namespace App\RateLimit;

use JacyImp\ApiPlatformRateLimiter\Contract\BucketResolverInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class TenantBucketResolver implements BucketResolverInterface
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
use JacyImp\ApiPlatformRateLimiter\Contract\BucketResolverInterface;

final readonly class TenantBucketResolver implements BucketResolverInterface
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

Use the same resolver on every operation that should share the tenant quota:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\RateLimit\TenantBucketResolver;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicBucket;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new GetCollection(
            extraProperties: [
                RateLimit::class => new RateLimit(
                    bucket: new DynamicBucket(TenantBucketResolver::class),
                    limit: 1000,
                    interval: '1 minute',
                ),
            ],
        ),
        new Get(
            extraProperties: [
                RateLimit::class => new RateLimit(
                    bucket: new DynamicBucket(TenantBucketResolver::class),
                    limit: 1000,
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

All users and both operations consume the current tenant's shared bucket. Add a [tenant + user composite identity](identities.md#composite-identities) when each user should have a separate counter inside that bucket.

## Select a configured bucket by plan

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
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicBucket;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new GetCollection(
            extraProperties: [
                RateLimit::class => new RateLimit(
                    bucket: new DynamicBucket(PlanBucketResolver::class),
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

Use this approach when plan definitions should live in configuration. Use `DynamicLimit` when the calculation belongs in application code or does not map cleanly to named plans.

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

Use `DynamicCost` in the API Platform operation:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\RateLimit\SearchCostResolver;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicCost;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/products/search',
            extraProperties: [
                RateLimit::class => new RateLimit(
                    bucket: 'catalog-search',
                    limit: 1000,
                    interval: '1 minute',
                    cost: new DynamicCost(SearchCostResolver::class),
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
    'bucket' => [
        App\RateLimit\TenantBucketResolver::class,
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
