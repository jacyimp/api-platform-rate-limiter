# API Platform Rate Limiter

[![CI](https://github.com/jacyimp/api-platform-rate-limiter/actions/workflows/ci.yml/badge.svg)](https://github.com/jacyimp/api-platform-rate-limiter/actions/workflows/ci.yml)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen)](https://github.com/jacyimp/api-platform-rate-limiter/actions/workflows/ci.yml)

Rate limiting for API Platform applications on Symfony and Laravel.

> This package is pre-1.0. Its public API may still change between releases.

## Requirements

- PHP 8.2+
- API Platform metadata 3.4 or 4.x
- Symfony 6.4, 7.x or 8.x
- or Laravel 11.x, 12.x or 13.x with API Platform for Laravel

## Quick start

### Symfony

```bash
composer require jacyimp/api-platform-rate-limiter
```

```php
// config/bundles.php

use JacyImp\ApiPlatformRateLimiter\Symfony\ApiPlatformRateLimiterBundle;

return [
    // ...
    ApiPlatformRateLimiterBundle::class => ['all' => true],
];
```

### Laravel

```bash
composer require api-platform/laravel jacyimp/api-platform-rate-limiter
```

Laravel package discovery registers the package automatically.

Publish the config when you need globals, configured buckets, custom storage, providers, bypasses, or runtime resolvers:

```bash
php artisan vendor:publish --tag=api-platform-rate-limiter-config
```

### Add your first limit

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(
            extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 100,
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

The item `GET` is limited to 100 requests per minute per resolved identity. The collection operation is unaffected.

No package configuration is required for operation-local limits.

## Cookbook

### Limit every operation on a resource

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
    ],
    extraProperties: [
        RateLimit::class => new RateLimit(
            limit: 100,
            interval: '1 minute',
        ),
    ],
)]
final class Product
{
    // ...
}
```

Override it for one operation:

```php
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(
            extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 500,
                    interval: '1 minute',
                ),
            ],
        ),
        new Post(),
    ],
    extraProperties: [
        RateLimit::class => new RateLimit(
            limit: 100,
            interval: '1 minute',
        ),
    ],
)]
final class Product
{
    // ...
}
```

### Limit the whole API

Symfony:

```yaml
# config/packages/api_platform_rate_limiter.yaml

api_platform_rate_limiter:
    globals:
        burst:
            limit: 100
            interval: '1 minute'

        daily:
            limit: 10000
            interval: '1 day'
```

Laravel:

```php
// config/api-platform-rate-limiter.php

'globals' => [
    'burst' => [
        'limit' => 100,
        'interval' => '1 minute',
    ],
    'daily' => [
        'limit' => 10000,
        'interval' => '1 day',
    ],
],
```

A resource can still add a tighter local limit:

```php
#[ApiResource(
    operations: [
        new Get(
            extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 20,
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

### Share one quota across operations

Configure the bucket once:

```yaml
# config/packages/api_platform_rate_limiter.yaml

api_platform_rate_limiter:
    buckets:
        catalog:
            limit: 1000
            interval: '1 minute'
```

Then reference it from multiple operations:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new GetCollection(
            extraProperties: [
                RateLimit::class => new RateLimit(bucket: 'catalog'),
            ],
        ),
        new Get(
            extraProperties: [
                RateLimit::class => new RateLimit(bucket: 'catalog'),
            ],
        ),
    ],
)]
final class Product
{
    // ...
}
```

Or define the shared bucket inline:

```php
#[ApiResource(
    operations: [
        new GetCollection(
            extraProperties: [
                RateLimit::class => new RateLimit(
                    bucket: 'catalog',
                    limit: 1000,
                    interval: '1 minute',
                ),
            ],
        ),
        new Get(
            extraProperties: [
                RateLimit::class => new RateLimit(
                    bucket: 'catalog',
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

Laravel:

```php
// config/api-platform-rate-limiter.php

'buckets' => [
    'catalog' => [
        'limit' => 1000,
        'interval' => '1 minute',
    ],
],
```

### Combine several limits on one operation

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new Get(
            extraProperties: [
                RateLimit::class => [
                    new RateLimit(
                        limit: 20,
                        interval: '1 minute',
                    ),
                    new RateLimit(bucket: 'catalog'),
                ],
            ],
        ),
    ],
)]
final class Product
{
    // ...
}
```

### Charge expensive operations more

```yaml
api_platform_rate_limiter:
    buckets:
        catalog:
            limit: 1000
            interval: '1 minute'
```

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new GetCollection(
            name: 'product_list',
            extraProperties: [
                RateLimit::class => new RateLimit(
                    bucket: 'catalog',
                    cost: 1,
                ),
            ],
        ),
        new GetCollection(
            uriTemplate: '/products/export',
            name: 'product_export',
            extraProperties: [
                RateLimit::class => new RateLimit(
                    bucket: 'catalog',
                    cost: 10,
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

### Bypass rate limiting on an operation

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use JacyImp\ApiPlatformRateLimiter\Metadata\BypassRateLimit;

#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(
            extraProperties: [
                BypassRateLimit::class => new BypassRateLimit(),
            ],
        ),
    ],
)]
final class Product
{
    // ...
}
```

Bypass only one shared bucket:

```php
#[ApiResource(
    operations: [
        new Get(
            extraProperties: [
                BypassRateLimit::class => new BypassRateLimit(
                    bucket: 'catalog',
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

Bypass one global:

```php
#[ApiResource(
    operations: [
        new Get(
            extraProperties: [
                BypassRateLimit::class => new BypassRateLimit(
                    bucket: 'global:burst',
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

### Use a custom identity

Resolver:

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
            ? 'api-key:' . $apiKey
            : null;
    }
}
```

API Platform resource:

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

The default identity is the authenticated user, falling back to client IP.

### Apply a limit conditionally

Condition:

```php
<?php

namespace App\RateLimit;

use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class AuthenticatedCondition implements RateLimitConditionInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function matches(): bool
    {
        return $this->tokenStorage->getToken()?->getUser() !== null;
    }
}
```

API Platform resource:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\RateLimit\AuthenticatedCondition;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Condition;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new GetCollection(
            extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 100,
                    interval: '1 minute',
                    when: new Condition(AuthenticatedCondition::class),
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

### Use a fixed window

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;

#[ApiResource(
    operations: [
        new Post(
            extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 10,
                    interval: '1 minute',
                    policy: RateLimitPolicy::FIXED_WINDOW,
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

The default is `RateLimitPolicy::SLIDING_WINDOW`.

### Make the limit dynamic

Resolver:

```php
<?php

namespace App\RateLimit;

use JacyImp\ApiPlatformRateLimiter\Contract\LimitResolverInterface;

final readonly class PlanLimitResolver implements LimitResolverInterface
{
    public function __construct(
        private SubscriptionContext $subscription,
    ) {
    }

    public function resolve(): int
    {
        return match ($this->subscription->plan()) {
            'free' => 100,
            'premium' => 1000,
            'enterprise' => 10000,
        };
    }
}
```

API Platform resource:

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

The same resolver can be used by a global:

```yaml
api_platform_rate_limiter:
    globals:
        api:
            limit:
                resolver: App\RateLimit\PlanLimitResolver
            interval: '1 minute'
```

### Make the bucket dynamic

Resolver:

```php
<?php

namespace App\RateLimit;

use JacyImp\ApiPlatformRateLimiter\Contract\BucketResolverInterface;

final readonly class TenantBucketResolver implements BucketResolverInterface
{
    public function __construct(
        private TenantContext $tenant,
    ) {
    }

    public function resolve(): string
    {
        return 'tenant:' . $this->tenant->id();
    }
}
```

API Platform resource:

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
    // Both operations share the resolved tenant bucket.
}
```

A dynamic bucket can also choose a configured bucket.

Resolver:

```php
<?php

namespace App\RateLimit;

use JacyImp\ApiPlatformRateLimiter\Contract\BucketResolverInterface;

final readonly class PlanBucketResolver implements BucketResolverInterface
{
    public function __construct(
        private SubscriptionContext $subscription,
    ) {
    }

    public function resolve(): string
    {
        return $this->subscription->plan();
    }
}
```

Configured buckets:

```yaml
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

API Platform resource:

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

### Make request cost dynamic

Resolver:

```php
<?php

namespace App\RateLimit;

use JacyImp\ApiPlatformRateLimiter\Contract\CostResolverInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class RequestCostResolver implements CostResolverInterface
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

API Platform resource:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\RateLimit\RequestCostResolver;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicCost;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new GetCollection(
            extraProperties: [
                RateLimit::class => new RateLimit(
                    bucket: 'catalog',
                    limit: 1000,
                    interval: '1 minute',
                    cost: new DynamicCost(RequestCostResolver::class),
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

### Combine conditions

Second condition:

```php
<?php

namespace App\RateLimit;

use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class InternalRequestCondition implements RateLimitConditionInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function matches(): bool
    {
        return $this->requestStack
            ->getCurrentRequest()
            ?->headers
            ->has('X-Internal') ?? false;
    }
}
```

Use both conditions:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\RateLimit\AuthenticatedCondition;
use App\RateLimit\InternalRequestCondition;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AllOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Condition;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Not;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new GetCollection(
            extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 100,
                    interval: '1 minute',
                    when: new AllOf([
                        new Condition(AuthenticatedCondition::class),
                        new Not(new Condition(InternalRequestCondition::class)),
                    ]),
                ),
            ],
        ),
    ],
)]
final class Product
{
    // Limited only when authenticated and not internal.
}
```

Configured bucket:

```yaml
api_platform_rate_limiter:
    buckets:
        authenticated:
            limit: 100
            interval: '1 minute'
            when: App\RateLimit\AuthenticatedCondition
```

Configured global:

```yaml
api_platform_rate_limiter:
    globals:
        public_api:
            limit: 100
            interval: '1 minute'
            when:
                all_of:
                    - App\RateLimit\AuthenticatedCondition
                    - not: App\RateLimit\InternalRequestCondition
```

### Use identity fallback

Resolvers:

```php
<?php

namespace App\RateLimit;

use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use Symfony\Component\HttpFoundation\RequestStack;
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

API Platform resource:

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

### Bypass conditionally

Condition:

```php
<?php

namespace App\RateLimit;

use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class InternalRequestCondition implements RateLimitConditionInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function matches(): bool
    {
        return $this->requestStack
            ->getCurrentRequest()
            ?->headers
            ->has('X-Internal') ?? false;
    }
}
```

API Platform resource:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\RateLimit\InternalRequestCondition;
use JacyImp\ApiPlatformRateLimiter\Metadata\BypassRateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Condition;

#[ApiResource(
    operations: [
        new Get(
            extraProperties: [
                BypassRateLimit::class => new BypassRateLimit(
                    bucket: 'catalog',
                    when: new Condition(InternalRequestCondition::class),
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

### Bypass the entire request from infrastructure code

```php
<?php

namespace App\RateLimit;

use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitBypassInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class InternalTrafficBypass implements RateLimitBypassInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function shouldBypass(): bool
    {
        return $this->requestStack
            ->getCurrentRequest()
            ?->headers
            ->has('X-Internal') ?? false;
    }
}
```

Symfony autoconfigures implementations. Laravel:

```php
// config/api-platform-rate-limiter.php

'bypasses' => [
    App\RateLimit\InternalTrafficBypass::class,
],
```

### Build limits from arbitrary runtime state

API Platform resource:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;

#[ApiResource(
    operations: [
        new GetCollection(name: 'product_list'),
        new GetCollection(
            uriTemplate: '/products/export',
            name: 'product_export',
        ),
    ],
)]
final class Product
{
    // ...
}
```

Provider:

```php
<?php

namespace App\RateLimit;

use ApiPlatform\Metadata\Operation;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitProviderInterface;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

final readonly class SubscriptionRateLimitProvider implements RateLimitProviderInterface
{
    public function __construct(
        private SubscriptionContext $subscription,
    ) {
    }

    public function provide(Operation $operation): iterable
    {
        if ($operation->getName() !== 'product_export') {
            return [];
        }

        return [
            new RateLimit(
                limit: $this->subscription->exportLimit(),
                interval: '1 hour',
            ),
        ];
    }
}
```

Symfony autoconfigures providers. Laravel:

```php
// config/api-platform-rate-limiter.php

'providers' => [
    App\RateLimit\SubscriptionRateLimitProvider::class,
],
```

## Storage

### Symfony

Default storage uses `cache.app`.

```yaml
api_platform_rate_limiter:
    cache_pool: cache.rate_limiter
```

Or provide a Symfony RateLimiter storage service:

```yaml
api_platform_rate_limiter:
    storage: app.rate_limit_storage
```

The storage service must implement Symfony's `StorageInterface`.

### Laravel

```php
// config/api-platform-rate-limiter.php

'storage' => [
    'store' => 'rate_limits',
    'service' => null,
],
```

Or:

```php
'storage' => [
    'store' => null,
    'service' => App\RateLimit\Storage::class,
],
```

Use shared storage such as Redis when several application instances must share counters.

## Rejection response

The default rejection is `429 Too Many Requests` with:

```text
Retry-After
RateLimit-Limit
RateLimit-Remaining
```

Custom handler:

```php
<?php

namespace App\RateLimit;

use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejection;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejectionHandlerInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class ApiRateLimitRejectionHandler implements RateLimitRejectionHandlerInterface
{
    public function reject(RateLimitRejection $rejection): never
    {
        throw new TooManyRequestsHttpException(
            message: 'API quota exceeded.',
            headers: [
                'RateLimit-Limit' => (string) $rejection->limit,
                'RateLimit-Remaining' => (string) $rejection->remaining,
            ],
        );
    }
}
```

Symfony:

```yaml
# config/services.yaml

services:
    JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejectionHandlerInterface:
        alias: App\RateLimit\ApiRateLimitRejectionHandler
```

All package exceptions implement `RateLimiterExceptionInterface`.

## Laravel resolver registration

Symfony autoconfigures the resolver contracts used above. Laravel lists selectable resolvers in the published config:

```php
// config/api-platform-rate-limiter.php

'resolvers' => [
    'identity' => [
        App\RateLimit\ApiKeyIdentityResolver::class,
        App\RateLimit\UserIdentityResolver::class,
        App\RateLimit\IpIdentityResolver::class,
    ],
    'condition' => [
        App\RateLimit\AuthenticatedCondition::class,
        App\RateLimit\InternalRequestCondition::class,
    ],
    'bucket' => [
        App\RateLimit\TenantBucketResolver::class,
        App\RateLimit\PlanBucketResolver::class,
    ],
    'limit' => [
        App\RateLimit\PlanLimitResolver::class,
    ],
    'cost' => [
        App\RateLimit\RequestCostResolver::class,
    ],
],
```

## Counter behavior

Counter identity is based on:

```text
bucket + identity + policy + limit + interval
```

A changed dynamic limit selects a different counter. `cost` changes consumption, not counter identity.

## Lifecycle events

Symfony listener:

```php
<?php

namespace App\EventListener;

use JacyImp\ApiPlatformRateLimiter\Event\RateLimitRejected;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class RateLimitMetricsListener
{
    #[AsEventListener]
    public function onRejected(RateLimitRejected $event): void
    {
        // Record metrics, logs, traces, etc.
    }
}
```

Available events:

```text
RateLimitChecking
RateLimitConsumed
RateLimitRejected
```

Laravel receives the same event classes through Laravel's event dispatcher.

## Intervals

```php
#[ApiResource(
    operations: [
        new GetCollection(
            extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 1000,
                    interval: '1 hour',
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

`DateInterval` and the package `Interval` value object are also supported:

```php
use DateInterval;
use JacyImp\ApiPlatformRateLimiter\Metadata\Interval;

#[ApiResource(
    operations: [
        new Get(
            extraProperties: [
                RateLimit::class => [
                    new RateLimit(
                        limit: 100,
                        interval: new DateInterval('PT1M'),
                    ),
                    new RateLimit(
                        limit: 1000,
                        interval: new Interval(hours: 1),
                    ),
                ],
            ],
        ),
    ],
)]
final class Product
{
    // ...
}
```

Intervals must be at least one second. Months, years, negative values, and fractional seconds are not supported.

## Development

```bash
composer require jacyimp/api-platform-rate-limiter:dev-main
```

```bash
composer check
composer audit
```

Individual checks:

```text
composer cs
composer analyse
composer test
composer test:behaviour
```

## License

MIT
