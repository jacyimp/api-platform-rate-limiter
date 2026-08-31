# API Platform Rate Limiter

Extensible rate limiting for API Platform applications.

This package provides operation-specific and shared rate limits for API Platform while keeping identity resolution and bypass rules extensible.

> Early development. Public APIs may still change before the first stable release.

## Requirements

- PHP 8.2+
- API Platform 3.4 or 4.x
- Symfony 6.4, 7.x or 8.x

## Installation

The package is not yet available on Packagist.

For development installs directly from GitHub:

```bash
composer config repositories.api-platform-rate-limiter vcs https://github.com/jacyimp/api-platform-rate-limiter.git
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

## Operation-specific limits

Rate-limit metadata is attached to API Platform operations through `extraProperties`.

```php
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use JacyImp\ApiPlatformRateLimiter\Metadata\OperationRateLimit;

#[ApiResource(
    operations: [
        new Get(
            extraProperties: [
                OperationRateLimit::class => new OperationRateLimit(
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

The limit above applies independently to that operation.

The default policy is `sliding_window`.

A fixed window can be selected explicitly:

```php
use JacyImp\ApiPlatformRateLimiter\Metadata\OperationRateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;

new OperationRateLimit(
    limit: 100,
    interval: '1 minute',
    policy: RateLimitPolicy::FIXED_WINDOW,
);
```

## Intervals

Operation-specific limits accept:

```php
new OperationRateLimit(
    limit: 100,
    interval: '1 minute',
);
```

A PHP `DateInterval`:

```php
new OperationRateLimit(
    limit: 100,
    interval: new DateInterval('PT1M'),
);
```

Or the package interval value object:

```php
use JacyImp\ApiPlatformRateLimiter\Metadata\Interval;

new OperationRateLimit(
    limit: 100,
    interval: new Interval(
        minutes: 1,
    ),
);
```

## Shared limits

Shared buckets are useful when several operations should consume the same rate-limit quota.

Configure them centrally:

```yaml
# config/packages/api_platform_rate_limiter.yaml

api_platform_rate_limiter:
    shared_buckets:
        catalog:
            limit: 1000
            interval: '1 minute'
            policy: sliding_window

        expensive:
            limit: 20
            interval: '1 hour'
            policy: fixed_window
```

Reference a shared bucket from an API Platform operation:

```php
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use JacyImp\ApiPlatformRateLimiter\Metadata\SharedRateLimit;

#[ApiResource(
    operations: [
        new Get(
            extraProperties: [
                SharedRateLimit::class => new SharedRateLimit(
                    'catalog',
                ),
            ],
        ),
    ],
)]
final class Product
{
}
```

Every request using the `catalog` bucket consumes from the same quota for the resolved identity.

## Combining limits

An operation may have both an operation-specific limit and a shared limit:

```php
use ApiPlatform\Metadata\Get;
use JacyImp\ApiPlatformRateLimiter\Metadata\OperationRateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\SharedRateLimit;

new Get(
    extraProperties: [
        OperationRateLimit::class => new OperationRateLimit(
            limit: 20,
            interval: '1 minute',
        ),
        SharedRateLimit::class => new SharedRateLimit(
            'catalog',
        ),
    ],
);
```

The request must satisfy every applicable limit.

## Identity resolution

The default Symfony identity resolver uses:

1. the authenticated Symfony user's identifier, when available;
2. otherwise the client's IP address.

The generated identities are namespaced internally:

```text
user:user@example.com
ip:192.0.2.1
```

Client IP resolution uses Symfony's trusted-proxy handling.

### Custom identity resolver

Implement:

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

Then alias it in your application container:

```yaml
# config/services.yaml

services:
    JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface:
        alias: App\RateLimit\ApiKeyIdentityResolver
```

## Bypassing rate limits

Applications can provide custom bypass rules.

For example, trusted internal traffic, verified crawlers, or another application-specific condition can decide not to consume a limit.

```php
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitBypassInterface;
use JacyImp\ApiPlatformRateLimiter\Core\ResolvedRateLimit;

final class TrustedRequestBypass implements RateLimitBypassInterface
{
    public function shouldBypass(
        ResolvedRateLimit $rateLimit,
    ): bool {
        // Application-specific verification.

        return false;
    }
}
```

Implementations discovered through Symfony autoconfiguration are automatically collected by the package.

The package itself deliberately contains no crawler-detection or crawler-verification logic.

## Rate-limit responses

When a limit is exceeded, the package throws a `429 Too Many Requests` HTTP exception.

The response includes:

```text
Retry-After
RateLimit-Limit
RateLimit-Remaining
```

## Storage

The Symfony integration stores limiter state through Symfony's cache system.

By default, it uses the application's `cache.app` pool.

For production systems with multiple application instances, configure `cache.app` with shared storage such as Redis rather than per-node filesystem storage.

## Development

Run all checks:

```bash
composer check
```

Run individual checks:

```bash
composer cs
composer analyse
composer test
composer test:behaviour
```

Run dependency security checks:

```bash
composer audit
```

## Status

The core rate-limiting flow is implemented and covered by unit and Symfony kernel integration tests.

The package has not yet reached its first stable release.

## License

MIT
