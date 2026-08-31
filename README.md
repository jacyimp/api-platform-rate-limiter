# API Platform Rate Limiter

Operation-specific and shared rate limits for API Platform applications.

> This package is pre-1.0. Its public API may still change between releases.

## Requirements

- PHP 8.2+
- API Platform 3.4 or 4.x
- Symfony 6.4, 7.x or 8.x

## Installation

For tagged releases:

```bash
composer require jacyimp/api-platform-rate-limiter
```

To install the current development branch instead:

```bash
composer require jacyimp/api-platform-rate-limiter:dev-main
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

No other configuration is required for operation-specific limits.

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
and `global:daily` above). The optional `policy`, `identity_resolver`, and
`when` settings work the same way as they do for shared buckets. The policy
defaults to `sliding_window`.

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
    shared_buckets:
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

Costs are per `RateLimit`, not per bucket. This lets operations consume
different token amounts from the same shared bucket:

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
    public function resolve(): string
    {
        return 'api-key:...';
    }
}
```

```yaml
# config/services.yaml

services:
    JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface:
        alias: App\RateLimit\ApiKeyIdentityResolver
```

Override the identity for one operation with `identityResolver`:

```php
new RateLimit(
    limit: 5,
    interval: '1 minute',
    identityResolver: ApiKeyIdentityResolver::class,
);
```

Other limits still use the global resolver.

## Conditional limits

Use `when` to apply a limit conditionally:

```php
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class InternalRequestCondition implements RateLimitConditionInterface
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    public function shouldApply(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request?->headers->has('X-Internal') ?? false;
    }
}

new RateLimit(
    limit: 5,
    interval: '1 minute',
    when: InternalRequestCondition::class,
);
```

The same per-operation overrides are available for a configured shared bucket:

```php
new RateLimit(
    bucket: 'otp',
    identityResolver: ApiKeyIdentityResolver::class,
    when: InternalRequestCondition::class,
);
```

When omitted, a shared limit uses the strategies configured on its shared
bucket.

`true` applies the limit; `false` skips it. Without `when`, the limit always
applies.

Shared buckets support both options:

```yaml
api_platform_rate_limiter:
    shared_buckets:
        otp:
            limit: 5
            interval: '1 minute'
            identity_resolver: App\RateLimit\ApiKeyIdentityResolver
            when: App\RateLimit\InternalRequestCondition
```

## Dynamic buckets, limits, and costs

Use the small descriptors when a value must be decided for each request. Their
resolver properties are Symfony service IDs; class names are convenient when
autoconfiguration is enabled:

```php
use JacyImp\ApiPlatformRateLimiter\Contract\BucketResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\DynamicCostResolverInterface;
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

final class RequestCostResolver implements DynamicCostResolverInterface
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

With autoconfiguration disabled, use
`jacyimp.api_platform_rate_limiter.bucket_resolver` and
`jacyimp.api_platform_rate_limiter.limit_resolver`, and
`jacyimp.api_platform_rate_limiter.cost_resolver` respectively.

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

Use `when` with a `RateLimitConditionInterface` service to make either form
conditional:

```php
extraProperties: [
    BypassRateLimit::class => new BypassRateLimit(
        bucket: 'catalog',
        when: InternalRequestCondition::class,
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

Limiter state is stored in Symfony's `cache.app` pool. In a multi-instance
deployment, configure that pool to use shared storage such as Redis.

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
