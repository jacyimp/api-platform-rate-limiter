# Quotas and shared limits

Use operation metadata for endpoint quotas, resource metadata for a whole resource, and named buckets when several operations must spend from one allowance.

## Limit one operation

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

Only the item `GET` is limited. Its counter is separate from every other operation-local limit.

## Limit every operation on a resource

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

API Platform supplies the resource metadata to each operation. Add operation-level metadata when one operation needs a different declaration.

## Limit the whole API

Each configured global applies to every API Platform operation.

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

The two globals are independent: every request must pass both `burst` and `daily`.

## Configured shared buckets

Define a shared quota centrally:

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

Reference it wherever requests should consume the same counter:

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

Configured buckets keep the quota definition in one place and are the best choice when many resources share it.

## Inline shared buckets

For a small number of operations, repeat the complete definition with the same bucket name:

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

Keep `bucket`, `limit`, `interval`, and `policy` identical when the declarations are intended to share one counter.

## Multiple limits on one operation

Use a list for burst and sustained quotas, or for local and shared quotas together:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new Post(
            extraProperties: [
                RateLimit::class => [
                    new RateLimit(
                        limit: 5,
                        interval: '1 minute',
                    ),
                    new RateLimit(
                        limit: 100,
                        interval: '1 hour',
                    ),
                    new RateLimit(bucket: 'catalog'),
                ],
            ],
        ),
    ],
)]
final class Order
{
    // ...
}
```

Limits are consumed sequentially in declaration order. Earlier successful consumption is not rolled back if a later limit rejects the request.

## Weighted request cost

Use `cost` when some requests should consume more of the same quota:

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

The list costs one token and the export costs ten. See [Plans, tenants, and dynamic quotas](plans-and-tenants.md#dynamic-request-cost) when cost depends on the request.

## Sliding and fixed windows

Sliding window is the default and smooths traffic around interval boundaries:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new Post(
            extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 10,
                    interval: '1 minute',
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

Choose a fixed window for counters that reset at the end of each interval:

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

Configured limits use `policy: fixed_window` or `'policy' => 'fixed_window'`.

## Interval formats

Human-readable strings are the simplest form:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

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

PHP `DateInterval` and the package `Interval` value object are available for programmatic metadata:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use DateInterval;
use JacyImp\ApiPlatformRateLimiter\Metadata\Interval;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

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

Intervals must resolve to at least one second. Months, years, negative values, and fractional seconds are not supported. Symfony YAML configuration accepts interval strings; Laravel configuration also accepts `DateInterval` and `Interval` values at runtime.

## See also

- [Choosing who gets rate limited](identities.md)
- [Plans, tenants, and dynamic quotas](plans-and-tenants.md)
- [Storage and production deployment](deployment.md)
- [README](../README.md)
