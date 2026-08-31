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

## Bypass rules

Implement `RateLimitBypassInterface` for requests that should not consume any
limit:

```php
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitBypassInterface;

final class TrustedRequestBypass implements RateLimitBypassInterface
{
    public function shouldBypass(): bool
    {
        return false;
    }
}
```

Symfony autoconfiguration discovers bypass implementations automatically. A
request bypasses rate limiting when any implementation returns `true`.

## Responses and storage

When a limit is exceeded, the package returns `429 Too Many Requests` with
`Retry-After`, `RateLimit-Limit`, and `RateLimit-Remaining` headers.

Limiter state is stored in Symfony's `cache.app` pool. In a multi-instance
deployment, configure that pool to use shared storage such as Redis.

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
