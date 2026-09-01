# Storage and production deployment

Use storage shared by every application instance that must enforce the same counters. Keep rate-limit state in a dedicated cache pool or store so its retention and capacity can be managed independently.

## Symfony default storage

The Symfony bundle uses a Symfony RateLimiter `CacheStorage` backed by `cache.app` by default. Inline limits work without storage configuration:

```yaml
# config/packages/api_platform_rate_limiter.yaml

api_platform_rate_limiter: ~
```

The package keeps its storage service private and does not replace Symfony's generic RateLimiter storage aliases.

## Symfony dedicated cache pool

Define a pool and point the package at its service ID:

```yaml
# config/packages/cache.yaml

framework:
    cache:
        pools:
            cache.rate_limiter:
                adapter: cache.app

api_platform_rate_limiter:
    cache_pool: cache.rate_limiter
```

Use a dedicated pool in production when rate-limit state needs different storage, pruning, or monitoring from ordinary application cache entries.

## Custom Symfony RateLimiter storage

The `storage` option accepts any service implementing Symfony's `StorageInterface`. For example, explicitly register Symfony's `CacheStorage` and select it:

```yaml
# config/services.yaml

services:
    app.rate_limit_storage:
        class: Symfony\Component\RateLimiter\Storage\CacheStorage
        arguments:
            - '@cache.rate_limiter'
```

```yaml
# config/packages/api_platform_rate_limiter.yaml

api_platform_rate_limiter:
    storage: app.rate_limit_storage
```

Use the same option for an application storage adapter that implements `Symfony\Component\RateLimiter\Storage\StorageInterface` and preserves limiter state until its expiration.

## Laravel cache store

By default, the package uses Laravel's default cache store through its isolated storage adapter. Select a dedicated store in the published config:

```php
<?php

// config/api-platform-rate-limiter.php

return [
    // ...
    'storage' => [
        'store' => 'rate_limits',
        'service' => null,
    ],
];
```

Define `rate_limits` in Laravel's cache configuration:

```php
<?php

// config/cache.php

return [
    // ...
    'stores' => [
        // ...
        'rate_limits' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],
    ],
];
```

Package cache keys are prefixed and do not change Laravel's global cache bindings. The `storage.service` option is also available when the application container already provides a complete Symfony RateLimiter `StorageInterface` implementation.

## Redis and multi-instance deployments

Every instance serving the same quota must use the same backend. A local filesystem or in-memory cache gives each instance an independent allowance.

Symfony:

```yaml
# config/packages/cache.yaml

framework:
    cache:
        pools:
            cache.rate_limiter:
                adapter: cache.adapter.redis
                provider: '%env(REDIS_URL)%'

api_platform_rate_limiter:
    cache_pool: cache.rate_limiter
```

Laravel:

```php
<?php

// config/cache.php

return [
    // ...
    'stores' => [
        // ...
        'rate_limits' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],
    ],
];
```

```php
<?php

// config/api-platform-rate-limiter.php

return [
    // ...
    'storage' => [
        'store' => 'rate_limits',
        'service' => null,
    ],
];
```

Use the same Redis deployment and cache namespace across application instances that should share counters. Use separate cache namespaces when environments or independent applications must not share them.

## Default 429 response

A rejected request receives `429 Too Many Requests` and these headers:

```text
Retry-After
RateLimit-Limit
RateLimit-Remaining
```

`Retry-After` is formatted as an HTTP date. `RateLimit-Limit` is the rejected quota's limit and `RateLimit-Remaining` is its remaining token count.

Symfony throws the package's `RateLimitExceededException`, which extends Symfony's `TooManyRequestsHttpException`; the host exception renderer controls the body. Laravel returns this JSON body by default:

```json
{
    "message": "Rate limit exceeded."
}
```

## Custom rejection handler

Implement `RateLimitRejectionHandlerInterface` to control the exception or response.

Symfony:

```php
<?php

namespace App\RateLimit;

use DateTimeZone;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejection;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejectionHandlerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ApiRateLimitRejectionHandler implements RateLimitRejectionHandlerInterface
{
    public function reject(RateLimitRejection $rejection): never
    {
        $retryAfter = $rejection
            ->retryAfter
            ->setTimezone(new DateTimeZone('GMT'))
            ->format('D, d M Y H:i:s \\G\\M\\T');

        throw new HttpException(
            statusCode: 429,
            message: 'API quota exceeded.',
            headers: [
                'Retry-After' => $retryAfter,
                'RateLimit-Limit' => (string) $rejection->limit,
                'RateLimit-Remaining' => (string) $rejection->remaining,
            ],
        );
    }
}
```

Replace the bundle alias:

```yaml
# config/services.yaml

services:
    App\RateLimit\ApiRateLimitRejectionHandler: ~

    JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejectionHandlerInterface:
        alias: App\RateLimit\ApiRateLimitRejectionHandler
```

Laravel:

```php
<?php

namespace App\RateLimit;

use DateTimeZone;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejection;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejectionHandlerInterface;

final class ApiRateLimitRejectionHandler implements RateLimitRejectionHandlerInterface
{
    public function reject(RateLimitRejection $rejection): never
    {
        $retryAfter = $rejection
            ->retryAfter
            ->setTimezone(new DateTimeZone('GMT'))
            ->format('D, d M Y H:i:s \\G\\M\\T');

        throw new HttpResponseException(new JsonResponse(
            data: [
                'message' => 'API quota exceeded.',
                'retryAt' => $rejection->retryAfter->format(DATE_ATOM),
            ],
            status: 429,
            headers: [
                'Retry-After' => $retryAfter,
                'RateLimit-Limit' => (string) $rejection->limit,
                'RateLimit-Remaining' => (string) $rejection->remaining,
            ],
        ));
    }
}
```

Bind it in the application's service provider:

```php
<?php

namespace App\Providers;

use App\RateLimit\ApiRateLimitRejectionHandler;
use Illuminate\Support\ServiceProvider;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejectionHandlerInterface;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            RateLimitRejectionHandlerInterface::class,
            ApiRateLimitRejectionHandler::class,
        );
    }
}
```

Keep the standard status and headers unless clients have a coordinated alternative.

## See also

- [Quotas and shared limits](quotas.md)
- [Plans, tenants, and dynamic quotas](plans-and-tenants.md)
- [Extending the rate limiter](extending.md)
- [README](../README.md)
