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
use JacyImp\ApiPlatformRateLimiter\Metadata\SharedRateLimit;

new Get(
    extraProperties: [
        SharedRateLimit::class => new SharedRateLimit('catalog'),
    ],
);
```

The `policy` setting is optional and defaults to `sliding_window`. Supported
values are `sliding_window` and `fixed_window`.

## Combine limits

An operation can have both its own limit and a shared limit:

```php
new Get(
    extraProperties: [
        RateLimit::class => new RateLimit(
            limit: 20,
            interval: '1 minute',
        ),
        SharedRateLimit::class => new SharedRateLimit('catalog'),
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

Symfony autoconfiguration registers both service types. If autoconfiguration is
disabled, add the tags manually:

```yaml
services:
    App\RateLimit\ApiKeyIdentityResolver:
        tags: ['jacyimp.api_platform_rate_limiter.identity_resolver']

    App\RateLimit\InternalRequestCondition:
        tags: ['jacyimp.api_platform_rate_limiter.condition']
```

## Bypass rules

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
