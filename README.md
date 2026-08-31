# API Platform Rate Limiter

Flexible rate limiting for API Platform applications on Symfony and Laravel.

Define limits directly on API Platform operations/resources, share quotas across operations with buckets, add global API quotas, and resolve buckets, limits, identities, conditions, or costs at runtime.

> This package is pre-1.0. Its public API may still change between releases.

## Requirements

- PHP 8.2+
- API Platform metadata 3.4 or 4.x
- Symfony 6.4, 7.x or 8.x
- or Laravel 11.x, 12.x or 13.x with API Platform for Laravel

## Quick start

### Symfony

Install the package:

```bash
composer require jacyimp/api-platform-rate-limiter
```

Register the bundle:

```php
// config/bundles.php

use JacyImp\ApiPlatformRateLimiter\Symfony\ApiPlatformRateLimiterBundle;

return [
    // ...
    ApiPlatformRateLimiterBundle::class => ['all' => true],
];
```

Then add a limit to an API Platform operation:

```php
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
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
}
```

That operation now allows each resolved identity 100 requests per minute.

No package configuration is required for operation-local limits.

### Laravel

Install API Platform for Laravel and the package:

```bash
composer require api-platform/laravel jacyimp/api-platform-rate-limiter
```

Laravel package discovery registers the service provider and API Platform middleware automatically.

Use the same `RateLimit` metadata shown above.

Publish the package configuration only when you need globals, named buckets, custom storage, providers, bypasses, or runtime resolvers:

```bash
php artisan vendor:publish --tag=api-platform-rate-limiter-config
```

## The mental model

Most use cases are built from one metadata class:

```php
new RateLimit(
    limit: 100,
    interval: '1 minute',
)
```

A `RateLimit` has:

| Option | Purpose | Default |
| --- | --- | --- |
| `limit` | Maximum tokens in the interval | required unless referencing a configured bucket |
| `interval` | Window such as `1 minute` or `1 day` | required with `limit` |
| `bucket` | Share the same quota across operations | operation-specific |
| `cost` | Tokens consumed by one request | `1` |
| `identity` | Who the quota belongs to | authenticated user, then client IP |
| `when` | Apply only when a condition matches | always |
| `policy` | Window algorithm | `sliding_window` |

If several limits resolve for a request, all of them must allow it.

## Cookbook

### Limit every operation on a resource

Put the metadata on `ApiResource` instead of a single operation:

```php
#[ApiResource(
    extraProperties: [
        RateLimit::class => new RateLimit(
            limit: 100,
            interval: '1 minute',
        ),
    ],
)]
final class Product
{
}
```

### Share one quota across several operations

Give them the same bucket:

```php
new RateLimit(
    bucket: 'catalog',
    limit: 1000,
    interval: '1 minute',
)
```

Use the same declaration on every operation that should consume from that quota.

If you prefer to define shared quotas centrally, configure a named bucket:

```yaml
# config/packages/api_platform_rate_limiter.yaml

api_platform_rate_limiter:
    buckets:
        catalog:
            limit: 1000
            interval: '1 minute'
```

Then reference it without repeating the definition:

```php
new RateLimit(bucket: 'catalog')
```

### Add a global API quota

Globals apply to every API Platform operation and can be combined with operation/resource limits:

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

The two quotas are enforced independently.

Laravel uses the same structure in `config/api-platform-rate-limiter.php`:

```php
'globals' => [
    'burst' => ['limit' => 100, 'interval' => '1 minute'],
    'daily' => ['limit' => 10000, 'interval' => '1 day'],
],
```

### Combine multiple limits

Use a list when one operation needs several quotas:

```php
extraProperties: [
    RateLimit::class => [
        new RateLimit(limit: 20, interval: '1 minute'),
        new RateLimit(bucket: 'catalog'),
    ],
]
```

For example, this can enforce both a tight operation limit and a wider shared catalog quota.

### Charge expensive operations more

`cost` controls how many tokens a request consumes:

```php
new RateLimit(
    bucket: 'catalog',
    limit: 1000,
    interval: '1 minute',
    cost: 10,
)
```

Two operations can share the same bucket while consuming different amounts:

```php
new RateLimit(bucket: 'catalog', cost: 1);  // regular request
new RateLimit(bucket: 'catalog', cost: 10); // expensive export
```

When a configured bucket and its reference both define a cost, the costs are multiplied.

### Use a fixed window

The default policy is a sliding window. For a fixed window:

```php
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;

new RateLimit(
    limit: 100,
    interval: '1 minute',
    policy: RateLimitPolicy::FIXED_WINDOW,
)
```

Supported policies are `sliding_window` and `fixed_window`.

### Make the limit depend on the current user or plan

Use `DynamicLimit` when the numeric limit must be resolved per request:

```php
use JacyImp\ApiPlatformRateLimiter\Contract\LimitResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

final class PlanLimitResolver implements LimitResolverInterface
{
    public function resolve(): int
    {
        return 1000; // e.g. derive from the authenticated user's plan
    }
}

new RateLimit(
    limit: new DynamicLimit(PlanLimitResolver::class),
    interval: '1 minute',
)
```

Symfony autoconfigures resolver implementations. On Laravel, list them under `resolvers.limit` in the published package config.

Configured globals also support dynamic limits:

```yaml
api_platform_rate_limiter:
    globals:
        api:
            limit:
                resolver: App\RateLimit\PlanLimitResolver
            interval: '1 minute'
```

### Choose the bucket at runtime

Use `DynamicBucket` when requests should be partitioned into runtime-selected shared buckets:

```php
use JacyImp\ApiPlatformRateLimiter\Contract\BucketResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicBucket;

final class CustomerBucketResolver implements BucketResolverInterface
{
    public function resolve(): string
    {
        return 'customer:123';
    }
}

new RateLimit(
    bucket: new DynamicBucket(CustomerBucketResolver::class),
    limit: 1000,
    interval: '1 minute',
)
```

A dynamic bucket can also resolve the name of a centrally configured bucket when `limit` and `interval` are omitted.

`DynamicCost` works the same way for per-request token cost.

### Apply a limit only sometimes

Implement `RateLimitConditionInterface` and reference it with `Condition`:

```php
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Condition;

final class AuthenticatedCondition implements RateLimitConditionInterface
{
    public function matches(): bool
    {
        return true; // inspect application/request state
    }
}

new RateLimit(
    limit: 100,
    interval: '1 minute',
    when: new Condition(AuthenticatedCondition::class),
)
```

Conditions can be composed with `AllOf`, `AnyOf`, and `Not`.

Configured globals and buckets support the same idea:

```yaml
api_platform_rate_limiter:
    buckets:
        authenticated:
            limit: 100
            interval: '1 minute'
            when: App\RateLimit\AuthenticatedCondition
```

### Use a custom identity

By default, limits are per authenticated user. If no authenticated user is available, the client IP is used.

For a specific limit, reference an `IdentityResolverInterface` implementation:

```php
use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\Identity;

final class ApiKeyIdentityResolver implements IdentityResolverInterface
{
    public function resolve(): ?string
    {
        return 'api-key:example';
    }
}

new RateLimit(
    limit: 100,
    interval: '1 minute',
    identity: new Identity(ApiKeyIdentityResolver::class),
)
```

Use `FirstAvailableIdentity` for fallback chains and `CompositeIdentity` when several values together define the identity.

To replace the default identity globally on Symfony, alias `IdentityResolverInterface` to your implementation.

### Bypass rate limiting for an operation or resource

To bypass every resolved limit:

```php
use JacyImp\ApiPlatformRateLimiter\Metadata\BypassRateLimit;

extraProperties: [
    BypassRateLimit::class => new BypassRateLimit(),
]
```

To bypass only a specific bucket:

```php
extraProperties: [
    BypassRateLimit::class => new BypassRateLimit(bucket: 'catalog'),
]
```

Global buckets use their final names, for example `global:burst`.

A bypass can also have a `when` condition.

For request-wide infrastructure bypasses such as trusted internal traffic, implement `RateLimitBypassInterface`. If any registered bypass returns `true`, rate limiting is skipped for the request.

### Build limits from arbitrary runtime state

For cases that do not fit the focused dynamic resolvers, implement `RateLimitProviderInterface`:

```php
use ApiPlatform\Metadata\Operation;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitProviderInterface;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

final class SubscriptionRateLimitProvider implements RateLimitProviderInterface
{
    public function provide(Operation $operation): iterable
    {
        return [
            new RateLimit(
                limit: 1000,
                interval: '1 minute',
            ),
        ];
    }
}
```

Providers are useful when runtime state decides whether a complete limit declaration exists at all. Prefer `DynamicLimit`, `DynamicBucket`, identities, conditions, and `DynamicCost` for narrower variations.

## Intervals

Human-readable strings are recommended:

```php
new RateLimit(limit: 100, interval: '30 seconds');
new RateLimit(limit: 100, interval: '1 minute');
new RateLimit(limit: 1000, interval: '1 hour');
```

`DateInterval` and the package `Interval` value object are also supported:

```php
use DateInterval;
use JacyImp\ApiPlatformRateLimiter\Metadata\Interval;

new RateLimit(limit: 100, interval: new DateInterval('PT1M'));
new RateLimit(limit: 100, interval: new Interval(minutes: 1));
```

Intervals must be at least one second. Months, years, negative values, and fractional seconds are not supported.

## Rejection response

When a limit is exceeded, the default handler returns `429 Too Many Requests` and includes:

- `Retry-After`
- `RateLimit-Limit`
- `RateLimit-Remaining`

To customize rejection behavior, implement `RateLimitRejectionHandlerInterface` and replace the default service binding/alias.

All package exceptions implement `RateLimiterExceptionInterface`.

## Storage

### Symfony

Limiter state uses `cache.app` by default.

Use a dedicated cache pool when needed:

```yaml
api_platform_rate_limiter:
    cache_pool: cache.rate_limiter
```

Or provide a Symfony RateLimiter storage service directly:

```yaml
api_platform_rate_limiter:
    storage: app.rate_limit_storage
```

The service must implement Symfony's `StorageInterface`.

### Laravel

The published config supports either a Laravel cache store or a Symfony RateLimiter storage service:

```php
'storage' => [
    'store' => 'rate_limits',
    'service' => null,
],
```

For multi-instance deployments, use shared storage such as Redis.

## Counter behavior

A persisted counter is selected from the resolved:

```text
bucket + identity + policy + limit + interval
```

This matters for dynamic limits: if the resolved limit changes, the request uses a different counter. Returning to the previous resolved definition can reuse its still-live counter.

`cost` is not part of counter identity. Different request costs can consume from the same counter.

Package bucket namespaces are isolated:

```text
operation:<operation key>
shared:<bucket>
global:<name>
```

Dynamic global partitions are nested under the named global.

## Lifecycle events

The package dispatches immutable PSR-14 events that can be used for metrics, logging, tracing, or auditing:

- `RateLimitChecking`
- `RateLimitConsumed`
- `RateLimitRejected`

They are observational and do not change enforcement behavior.

## Symfony autoconfiguration

Symfony automatically discovers implementations of the main extension contracts, including:

- `RateLimitConditionInterface`
- `IdentityResolverInterface`
- `BucketResolverInterface`
- `LimitResolverInterface`
- `CostResolverInterface`
- `RateLimitProviderInterface`
- `RateLimitBypassInterface`

Laravel does not have Symfony-style interface autoconfiguration. Register selectable resolvers, providers, and bypasses in the published package config.

## Development

To install the current development branch:

```bash
composer require jacyimp/api-platform-rate-limiter:dev-main
```

Run the full local checks with:

```bash
composer check
composer audit
```

Individual commands are available through `composer cs`, `composer analyse`, `composer test`, and `composer test:behaviour`.

## License

MIT
