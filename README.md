# API Platform Rate Limiter

[![CI](https://github.com/jacyimp/api-platform-rate-limiter/actions/workflows/ci.yml/badge.svg)](https://github.com/jacyimp/api-platform-rate-limiter/actions/workflows/ci.yml)
[![PHPStan level max](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg)](https://phpstan.org/)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen)](https://github.com/jacyimp/api-platform-rate-limiter/actions/workflows/ci.yml)
[![Mutation Score](https://img.shields.io/badge/MSI-100%25-brightgreen)](https://github.com/jacyimp/api-platform-rate-limiter/actions/workflows/ci.yml)

Rate limiting for API Platform applications on Symfony and Laravel.

Define quotas next to API Platform operations, share a quota across endpoints, or apply limits to the whole API. Requests are limited by authenticated user and then by client IP by default.

> This package is pre-1.0. Its public API may still change between releases.

## Requirements

- PHP 8.2+
- API Platform metadata 3.4 or 4.x
- Symfony 6.4, 7.x or 8.x
- or Laravel 11.x, 12.x or 13.x with API Platform for Laravel

## Symfony installation

```bash
composer require jacyimp/api-platform-rate-limiter
```

If Symfony Flex is not available, register the bundle manually in `config/bundles.php`:

```php
<?php

// config/bundles.php

use JacyImp\ApiPlatformRateLimiter\Symfony\ApiPlatformRateLimiterBundle;

return [
    // ...
    ApiPlatformRateLimiterBundle::class => ['all' => true],
];
```

Operation-local limits work immediately. No package configuration is required.

## Laravel installation

```bash
composer require api-platform/laravel jacyimp/api-platform-rate-limiter
```

Laravel package discovery registers the service provider and API Platform middleware automatically.

Publish the configuration when you need global limits, configured buckets, custom storage, providers, bypasses, or runtime resolvers:

```bash
php artisan vendor:publish --tag=api-platform-rate-limiter-config
```

## Add your first limit

Add `RateLimit` to an operation's `extraProperties`:

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

The item `GET` now allows 100 requests per minute for each resolved identity. The collection operation is unaffected.

## Who is counted

The default identity is:

1. the authenticated user identifier;
2. otherwise the client IP.

Symfony uses its trusted-proxy-aware `Request::getClientIp()` result and Laravel uses `Request::ip()`. Configure trusted proxies in the host framework and never parse forwarded IP headers yourself. See [Choosing who gets rate limited](docs/identities.md) for explicit IP, user, API key, tenant, fallback, and composite identities.

## Limit every operation on a resource

Put the metadata on the resource to apply it to all of its operations:

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

Operation metadata can override the resource-wide value:

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

## Limit the whole API

Configured globals apply to every API Platform operation.

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
<?php

// config/api-platform-rate-limiter.php

return [
    // ...
    'globals' => [
        'burst' => [
            'limit' => 100,
            'interval' => '1 minute',
        ],
        'daily' => [
            'limit' => 10_000,
            'interval' => '1 day',
        ],
    ],
];
```

Globals are independent quotas. A resource or operation can still add a tighter local limit.

## Share one quota across operations

Configure a named bucket once when several endpoints should consume the same counter.

Symfony:

```yaml
# config/packages/api_platform_rate_limiter.yaml

api_platform_rate_limiter:
    buckets:
        catalog:
            limit: 1000
            interval: '1 minute'
```

Laravel:

```php
<?php

// config/api-platform-rate-limiter.php

return [
    // ...
    'buckets' => [
        'catalog' => [
            'limit' => 1000,
            'interval' => '1 minute',
        ],
    ],
];
```

Reference that bucket from each operation:

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

Both operations consume the same 1,000-request quota.

For a small number of operations, the shared definition can stay inline. Repeat the same `bucket`, `limit`, and `interval` wherever the quota is used:

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

Prefer a configured bucket when the definition is used widely or should be changed centrally.

## Combine several limits

Use a list to enforce several quotas on one operation:

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

Limits are consumed in order. If a later limit rejects the request, consumption from earlier limits is not rolled back.

## Charge expensive requests more

`cost` is the number of tokens consumed by one request. This lets an export and an ordinary read share a quota without costing the same amount:

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

Here an export consumes 10 tokens from the configured `catalog` bucket.

## Rejection response

The default response is `429 Too Many Requests` with these headers:

```text
Retry-After
RateLimit-Limit
RateLimit-Remaining
```

`Retry-After` is an HTTP date. The body is rendered by the host framework; Laravel's default JSON body is `{"message":"Rate limit exceeded."}`. See [Storage and production deployment](docs/deployment.md#custom-rejection-handler) to replace the rejection handler.

## Guides

- [Quotas and shared limits](docs/quotas.md) — endpoint, resource, global, and shared quotas.
- [Choosing who gets rate limited](docs/identities.md) — users, IPs, API keys, tenants, fallback, and composite identities.
- [Plans, tenants, and dynamic quotas](docs/plans-and-tenants.md) — subscription tiers, dynamic limits, tenant buckets, and dynamic request costs.
- [Conditional limits and bypasses](docs/conditions-and-bypasses.md) — conditional rules, exempt endpoints, internal traffic, and trusted crawlers.
- [Storage and production deployment](docs/deployment.md) — storage, Redis/shared counters, framework configuration, and rejection responses.
- [Extending the rate limiter](docs/extending.md) — providers, events, custom handlers, framework registration, and counter internals.

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
composer mutation
```

## License

MIT
