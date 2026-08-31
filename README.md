# API Platform Rate Limiter

Operation-specific and shared rate limits for API Platform applications.

> This package is pre-1.0. Its public API may still change between releases.

## Requirements

- PHP 8.2+
- API Platform 3.4 or 4.x
- Symfony 6.4, 7.x or 8.x, or Laravel 11.x, 12.x or 13.x

## Installation

For tagged releases:

```bash
composer require jacyimp/api-platform-rate-limiter
```

To install the current development branch instead:

```bash
composer require jacyimp/api-platform-rate-limiter:dev-main
```

The metadata API and all rate-limit features are shared by both integrations.
Only framework bootstrap and configuration differ.

### Symfony + API Platform

Register the bundle:

```php
// config/bundles.php

use JacyImp\ApiPlatformRateLimiter\Symfony\ApiPlatformRateLimiterBundle;

return [
    // ...

    ApiPlatformRateLimiterBundle::class => ['all' => true],
];
```

No other configuration is required for operation-specific limits.

### Laravel + API Platform

```bash
composer require api-platform/laravel jacyimp/api-platform-rate-limiter
```

Laravel package discovery registers `LaravelServiceProvider`. It adds the
package middleware to API Platform's default operation middleware, after API
Platform has populated `_api_operation`. Requests without an API Platform
`Operation` are ignored; operation metadata is never reconstructed from a
Laravel route.

Publish configuration when advanced features are needed:

```bash
php artisan vendor:publish --tag=api-platform-rate-limiter-config
```

```php
// config/api-platform-rate-limiter.php

return [
    'globals' => [
        'burst' => ['limit' => 100, 'interval' => '1 minute'],
    ],
    'buckets' => [
        'catalog' => ['limit' => 1000, 'interval' => '1 minute'],
    ],
    'storage' => [
        'store' => 'rate_limits', // A store from config/cache.php.
        'service' => null, // Or a Symfony StorageInterface service ID.
    ],
    'providers' => [App\RateLimit\SubscriptionProvider::class],
    'bypasses' => [],
    'resolvers' => [
        'identity' => [App\RateLimit\ApiKeyIdentity::class],
        'condition' => [],
        'bucket' => [],
        'limit' => [App\RateLimit\PlanLimit::class],
        'cost' => [],
    ],
];
```

Laravel has no Symfony-style interface autoconfiguration. List multi-service
providers, bypasses, and selectable strategies here; each class is resolved
normally from Laravel's container. The default identity is the authenticated
user's auth identifier, falling back to Laravel's request IP.

The basic metadata is identical in both frameworks:

```php
new Get(extraProperties: [
    RateLimit::class => new RateLimit(limit: 100, interval: '1 minute'),
]);
```

## Limit the entire API

Configure one or more named quotas for every API Platform operation:

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

Each quota is shared across all operations for its resolved identity and is
enforced independently. Its bucket is named `global:<name>` (`global:burst`
and `global:daily` above). The optional `policy`, `identity`, `when`,
and `cost` settings have the same meaning as their `RateLimit` counterparts.
The policy defaults to `sliding_window`.

Globals use the same runtime resolution pipeline as operation and resource
limits. Resolver service IDs can therefore provide request-dependent values:

```yaml
api_platform_rate_limiter:
    globals:
        api:
            limit:
                resolver: App\RateLimit\PlanLimitResolver
            interval: '1 minute'
            bucket:
                resolver: App\RateLimit\PlanBucketResolver
            cost:
                resolver: App\RateLimit\RequestCostResolver
            identity: App\RateLimit\ApiKeyIdentityResolver
            when: App\RateLimit\ApiRequestCondition
```

A resolved dynamic bucket is nested below the configured global name. For
example, `premium` and `free` from the `api` resolver become
`global:api:premium` and `global:api:free`. This preserves the named global's
namespace and prevents collisions. A global may alternatively set `bucket` and
omit `limit` and `interval` to look up a configured shared-bucket definition;
its final bucket remains in the global namespace.

Composable conditions and identities use the same expression names in YAML:

```yaml
api_platform_rate_limiter:
    globals:
        authenticated_api:
            limit: 100
            interval: '1 minute'
            identity:
                composite:
                    - App\RateLimit\TenantIdentityResolver
                    - first_available:
                        - App\RateLimit\ApiKeyIdentityResolver
                        - App\RateLimit\UserIdentityResolver
            when:
                all_of:
                    - App\RateLimit\AuthenticatedCondition
                    - not: App\RateLimit\InternalRequestCondition
```

Identity operators are `composite` and `first_available`; condition operators
are `all_of`, `any_of`, and `not`. A plain service ID remains a leaf expression.

Global, operation-specific, and named shared limits are combined when more than
one applies to a request.

## Limit an operation

Add a `RateLimit` to the operation's `extraProperties`:

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

This allows each resolved identity 100 requests per minute for this operation.
The default policy is `sliding_window`.

To apply the same limit to every operation of a resource, define it on the
`ApiResource` instead:

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

Bucket-based `RateLimit` metadata can be defined at resource level in the same
way. Metadata on an individual operation overrides inherited metadata under the
same `RateLimit::class` key.

To use a fixed window instead:

```php
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;

new RateLimit(
    limit: 100,
    interval: '1 minute',
    policy: RateLimitPolicy::FIXED_WINDOW,
);
```

Human-readable interval strings are recommended. `DateInterval` and the
package's `Interval` value object are also supported:

```php
use DateInterval;
use JacyImp\ApiPlatformRateLimiter\Metadata\Interval;

new RateLimit(limit: 100, interval: new DateInterval('PT1M'));
new RateLimit(limit: 100, interval: new Interval(minutes: 1));
```

Intervals must be at least one second. Months, years, negative values, and
fractional seconds are not supported.

## Share a limit across operations

A shared bucket lets several operations consume the same quota. Define the
bucket in Symfony configuration:

```yaml
# config/packages/api_platform_rate_limiter.yaml

api_platform_rate_limiter:
    buckets:
        catalog:
            limit: 1000
            interval: '1 minute'
            policy: sliding_window
```

Then reference it from each operation that should share the quota:

```php
new Get(
    extraProperties: [
        RateLimit::class => new RateLimit(bucket: 'catalog'),
    ],
);
```

You can also define a shared bucket inline, without central configuration:

```php
new RateLimit(
    limit: 1000,
    interval: '1 minute',
    bucket: 'catalog',
);
```

The `policy` setting is optional and defaults to `sliding_window`. Supported
values are `sliding_window` and `fixed_window`.

## Consume weighted tokens

Set `cost` when a request should consume more than one token. It defaults to
`1` and must be a positive integer:

```php
new RateLimit(
    limit: 100,
    interval: '1 minute',
    cost: 5,
);
```

Costs can be declared on a configured bucket and refined by a reference. When
both are present, they are multiplied. This lets operations consume different
token amounts from the same shared bucket:

```php
new Get(
    name: 'product_get',
    extraProperties: [
        RateLimit::class => new RateLimit(bucket: 'catalog', cost: 1),
    ],
);

new Get(
    name: 'product_export',
    extraProperties: [
        RateLimit::class => new RateLimit(bucket: 'catalog', cost: 10),
    ],
);
```

The cost does not affect the storage key; both operations consume from the
same `catalog` quota for the resolved identity.

## Combine limits

An operation can have both its own limit and a shared limit:

```php
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
);
```

The request must satisfy both limits. The operation limit is consumed first,
followed by the shared limit. A successful consumption is not rolled back if a
later limit rejects the request.

## Identity resolution

By default, limits apply per authenticated Symfony user. When no authenticated
user is available, they apply per client IP. Client IPs are obtained through
Symfony's trusted-proxy handling.

To use another identity, implement `IdentityResolverInterface` and alias it in
the application container:

```php
use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;

final class ApiKeyIdentityResolver implements IdentityResolverInterface
{
    public function resolve(): ?string
    {
        return $apiKey === null ? null : 'api-key:' . $apiKey;
    }
}
```

```yaml
# config/services.yaml

services:
    JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface:
        alias: App\RateLimit\ApiKeyIdentityResolver
```

Override the identity for one operation with an identity expression. Resolver
implementations are autoconfigured as services:

```php
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\Identity;

new RateLimit(
    limit: 5,
    interval: '1 minute',
    identity: new Identity(ApiKeyIdentityResolver::class),
);
```

`null` from a resolver means that identity is unavailable for the current
request. Use `FirstAvailableIdentity` for ordered fallback:

```php
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\FirstAvailableIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\Identity;

identity: new FirstAvailableIdentity([
    new Identity(ApiKeyIdentityResolver::class),
    new Identity(UserIdentityResolver::class),
    new Identity(IpIdentityResolver::class),
])
```

Use `CompositeIdentity` when every component is required. Expressions can be
nested:

```php
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\CompositeIdentity;

identity: new CompositeIdentity([
    new Identity(TenantIdentityResolver::class),
    new FirstAvailableIdentity([
        new Identity(ApiKeyIdentityResolver::class),
        new Identity(UserIdentityResolver::class),
    ]),
])
```

Composite values use a deterministic length-prefixed encoding, so component
boundaries cannot collide. Omitting `identity` keeps the default authenticated
user → client IP behavior.

## Conditional limits

Use `when` to apply a limit conditionally:

```php
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AllOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Condition;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Not;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class InternalRequestCondition implements RateLimitConditionInterface
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    public function matches(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request?->headers->has('X-Internal') ?? false;
    }
}

new RateLimit(
    limit: 5,
    interval: '1 minute',
    when: new Condition(InternalRequestCondition::class),
);
```

The same per-operation overrides are available for a configured shared bucket:

```php
new RateLimit(
    bucket: 'otp',
    identity: new Identity(ApiKeyIdentityResolver::class),
    when: new Condition(InternalRequestCondition::class),
);
```

When omitted, a shared limit uses the strategies configured on its shared
bucket.

`true` applies the limit; `false` skips it. Without `when`, the limit always
applies.

Conditions can be composed with `AllOf`, `AnyOf`, and `Not`. Leaf service
references always use `Condition`:

```php
new RateLimit(
    limit: 5,
    interval: '1 minute',
    when: new AllOf([
        new Condition(AuthenticatedCondition::class),
        new Not(new Condition(TrustedCrawlerCondition::class)),
    ]),
);
```

Shared buckets support both options:

```yaml
api_platform_rate_limiter:
    buckets:
        otp:
            limit: 5
            interval: '1 minute'
            identity: App\RateLimit\ApiKeyIdentityResolver
            when: App\RateLimit\InternalRequestCondition
```

Configured `when` expressions use the same `all_of`, `any_of`, and `not`
structure as globals. A condition on a bucket reference is combined with the
configured condition, so both must match.

## Dynamic buckets, limits, and costs

Use the small descriptors when a value must be decided for each request. Their
resolver properties are Symfony service IDs; class names are convenient when
autoconfiguration is enabled:

```php
use JacyImp\ApiPlatformRateLimiter\Contract\BucketResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\CostResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\LimitResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicBucket;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicCost;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicLimit;

final class CustomerBucketResolver implements BucketResolverInterface
{
    public function resolve(): string
    {
        return 'customer:current';
    }
}

final class PlanLimitResolver implements LimitResolverInterface
{
    public function resolve(): int
    {
        return 500;
    }
}

final class RequestCostResolver implements CostResolverInterface
{
    public function resolve(): int
    {
        return 5;
    }
}

new RateLimit(
    limit: new DynamicLimit(PlanLimitResolver::class),
    interval: '1 minute',
    bucket: new DynamicBucket(CustomerBucketResolver::class),
    cost: new DynamicCost(RequestCostResolver::class),
);
```

A dynamic bucket with no inline `limit` and `interval` resolves the name of a
centrally configured shared bucket. Resolvers must return a non-empty bucket and
a positive limit and cost. Values are resolved before enforcement; storage
receives the resolved limit and enforcement consumes the resolved cost.

The resolved values define counter identity. Repeated resolutions of the same
dynamic bucket reuse a counter, while different bucket values partition it.
A change in a dynamic limit selects a different counter; returning to a
previous limit resumes that limit's existing counter. The same rule applies to
any interval or policy value resolved dynamically by future metadata support.
Dynamic cost is deliberately different: it never selects a counter and only
controls how many tokens the request consumes from the selected counter.

With autoconfiguration disabled, use
`jacyimp.api_platform_rate_limiter.bucket_resolver` and
`jacyimp.api_platform_rate_limiter.limit_resolver`, and
`jacyimp.api_platform_rate_limiter.cost_resolver` respectively.

## Runtime providers

Use first-class `Dynamic*`, identity, and condition metadata for common runtime
variation. Use `RateLimitProviderInterface` when arbitrary application state
must determine whether or how an entire `RateLimit` declaration is constructed:

```php
use ApiPlatform\Metadata\Operation;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitProviderInterface;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

final class SubscriptionRateLimitProvider implements RateLimitProviderInterface
{
    public function __construct(private SubscriptionContext $subscriptions)
    {
    }

    public function provide(Operation $operation): iterable
    {
        if (!$this->subscriptions->hasRequestLimit()) {
            return [];
        }

        return [new RateLimit(
            limit: $this->subscriptions->requestLimit(),
            interval: '1 minute',
        )];
    }
}
```

Providers are autoconfigured. Their limits use the same configured-bucket,
dynamic value, identity, condition, cost, policy, bypass, and enforcement path
as metadata limits. Resolution order is operation/resource metadata, providers
in Symfony tagged-service order (preserving each provider's iterable order),
then named globals. Declarations are not deduplicated: identical declarations
are consumed sequentially and, because their fully resolved counter identity is
the same, consume the same persisted counter. An empty iterable contributes no
limits, and provider or declaration-resolution exceptions propagate normally.

Avoid expanding providers with separate bucket, identity, cost, or condition
APIs; construct those capabilities on the returned `RateLimit` instead.

## Bypass rules

Use `BypassRateLimit` metadata to declaratively skip limits for a resource or
operation. With no bucket, it bypasses every resolved limit:

```php
use JacyImp\ApiPlatformRateLimiter\Metadata\BypassRateLimit;

extraProperties: [
    BypassRateLimit::class => new BypassRateLimit(),
]
```

Set `bucket` to bypass only limits whose final resolved bucket name matches.
This works uniformly for operation, shared, dynamic, and global buckets:

```php
extraProperties: [
    BypassRateLimit::class => new BypassRateLimit(bucket: 'global:burst'),
]
```

Use the same condition expressions with `when` to make either form conditional.
For bypass metadata, a matching expression means the bypass applies:

```php
extraProperties: [
    BypassRateLimit::class => new BypassRateLimit(
        bucket: 'catalog',
        when: new Condition(InternalRequestCondition::class),
    ),
]
```

Like `RateLimit`, bypass metadata can be a single value or a list, inherits
from resource metadata, and is overridden by the operation's value for the
same metadata key. Filtering happens after dynamic values are resolved and
before enforcement, so bypassed limits consume no tokens and dispatch no
rate-limit lifecycle events.

Global bypasses remain available for requests that should not consume any
limit. Implement `RateLimitBypassInterface`; Symfony autoconfiguration discovers
implementations automatically, and a request bypasses rate limiting when any
implementation returns `true`. With `autoconfigure: false`, use the
`jacyimp.api_platform_rate_limiter.bypass` tag.

## Responses and storage

When a limit is exceeded, the package returns `429 Too Many Requests` with
`Retry-After`, `RateLimit-Limit`, and `RateLimit-Remaining` headers.

Package failures implement `RateLimiterExceptionInterface`, so consumers can
catch the complete exception family or one of its specific exceptions:

- `InvalidRateLimitException`
- `InvalidIntervalException`
- `InvalidRateLimitMetadataException`
- `UndefinedSharedBucketException`
- `IdentityResolutionException`
- `RateLimitExceededException`

The validation exceptions remain `InvalidArgumentException` instances,
identity failures remain `RuntimeException` instances, and
`RateLimitExceededException` remains a Symfony
`TooManyRequestsHttpException`.

To customize rejection behavior, implement `RateLimitRejectionHandlerInterface`
and alias the contract to your service. The handler receives a public
`RateLimitRejection` value with the limit, remaining requests, and retry time,
and must throw an exception:

```php
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejection;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejectionHandlerInterface;

final class ApiRateLimitRejectionHandler implements RateLimitRejectionHandlerInterface
{
    public function reject(RateLimitRejection $rejection): never
    {
        throw new DomainException('API quota exceeded.');
    }
}
```

```yaml
# config/services.yaml

services:
    JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejectionHandlerInterface:
        alias: App\RateLimit\ApiRateLimitRejectionHandler
```

On Symfony, limiter state uses `cache.app` by default. Select a dedicated
cache pool when needed:

```yaml
api_platform_rate_limiter:
    cache_pool: cache.rate_limiter
```

You can alternatively provide a Symfony RateLimiter storage service directly:

```yaml
api_platform_rate_limiter:
    storage: app.rate_limit_storage
```

The service must implement Symfony's `StorageInterface`; the package does not
replace Symfony's generic interface alias. In a multi-instance
deployment, configure that pool to use shared storage such as Redis.

On Laravel, `storage.store` selects an isolated named store from
`config/cache.php`; `null` uses Laravel's default store. The package wraps that
repository for Symfony RateLimiter and does not replace Laravel's cache
bindings. `storage.service` may instead name a container service implementing
Symfony's `StorageInterface`.

### Counter identity

A persisted limiter counter is identified by exactly this concrete tuple:

```text
(final bucket, resolved identity, policy, limit, normalized interval seconds)
```

The tuple is encoded with collision-safe component boundaries and hashed before
it is passed to Symfony RateLimiter storage. Equal tuples share state; a change
to any tuple component selects distinct state. In particular, changing a
dynamic `limit`, interval, or policy does not reinterpret or reset an
incompatible counter. Returning to an earlier complete tuple resumes its
previous persisted state until that state expires.

Request `cost` is not part of counter identity: costs `1` and `10` consume one
and ten tokens from the same counter when the tuple above matches. Resolver
service IDs, conditions, unresolved metadata, and operation path, method, or
name are not separately appended to shared or global keys. Operation metadata
matters only when the operation key creates the final local bucket namespace:

- operation-local limits use `operation:<operation key>` and remain isolated;
- shared limits use `shared:<resolved bucket>` across every referencing
  operation;
- named globals use `global:<name>`, with dynamic partitions nested as
  `global:<name>:<resolved bucket>`.

Consequently, a shared and a global bucket with similar human-facing names do
not share state, and dynamic global partitions such as `global:api:free` and
`global:api:premium` remain independent.

## Lifecycle events

The package dispatches three immutable PSR-14 events for logging, metrics,
tracing, and auditing:

- `RateLimitChecking` immediately before a limit is consumed;
- `RateLimitConsumed` after a request is accepted by a limit;
- `RateLimitRejected` after a request is rejected by a limit.

All three expose the bucket, resolved identity, configured limit, interval in
seconds, and policy. The consumed and rejected events also expose the remaining
requests and retry time. Events are observational and cannot change enforcement
behavior.

Subscribe with Symfony's normal event listener support:

```php
use JacyImp\ApiPlatformRateLimiter\Event\RateLimitRejected;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class RateLimitMetricsListener
{
    #[AsEventListener]
    public function onRejected(RateLimitRejected $event): void
    {
        // Record $event->bucket, $event->identity, and $event->retryAfter.
    }
}
```

Laravel listeners subscribe to these same event classes through Laravel's
event dispatcher; the common PSR-14 event objects are dispatched unchanged.

## Development

Run the test suite and code-quality checks:

```bash
composer check
composer audit
```

Individual checks are available through `composer cs`, `composer analyse`,
`composer test`, and `composer test:behaviour`.

## License

MIT
