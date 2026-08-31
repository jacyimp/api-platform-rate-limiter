# API Platform Rate Limiter

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

Publish the config only when you need globals, configured buckets, custom storage, providers, bypasses, or runtime resolvers:

```bash
php artisan vendor:publish --tag=api-platform-rate-limiter-config
```

### Add your first limit

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

No package configuration is required for operation-local limits.

## Cookbook

### Limit every operation on a resource

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

An operation can override inherited `RateLimit` metadata by defining its own value under the same key.

### Share one quota across operations

Inline:

```php
new RateLimit(
    bucket: 'catalog',
    limit: 1000,
    interval: '1 minute',
)
```

Use the same bucket on every operation that should consume the same quota.

Or configure it once:

```yaml
# config/packages/api_platform_rate_limiter.yaml

api_platform_rate_limiter:
    buckets:
        catalog:
            limit: 1000
            interval: '1 minute'
```

```php
new RateLimit(bucket: 'catalog')
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

### Limit the whole API

Symfony:

```yaml
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

Globals, resource limits, operation limits, and shared buckets can all apply to the same request.

### Combine several limits

```php
extraProperties: [
    RateLimit::class => [
        new RateLimit(
            limit: 20,
            interval: '1 minute',
        ),
        new RateLimit(bucket: 'catalog'),
    ],
]
```

### Charge expensive operations more

```php
new RateLimit(
    bucket: 'catalog',
    limit: 1000,
    interval: '1 minute',
    cost: 10,
)
```

With a configured bucket:

```php
new RateLimit(bucket: 'catalog', cost: 1);  // regular request
new RateLimit(bucket: 'catalog', cost: 10); // export
```

Configured bucket cost and reference cost are multiplied.

### Use a fixed window

```php
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;

new RateLimit(
    limit: 100,
    interval: '1 minute',
    policy: RateLimitPolicy::FIXED_WINDOW,
)
```

The default is `RateLimitPolicy::SLIDING_WINDOW`.

### Make the limit dynamic

```php
use JacyImp\ApiPlatformRateLimiter\Contract\LimitResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicLimit;

final class PlanLimitResolver implements LimitResolverInterface
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

```php
new RateLimit(
    limit: new DynamicLimit(PlanLimitResolver::class),
    interval: '1 minute',
)
```

Dynamic global limit:

```yaml
api_platform_rate_limiter:
    globals:
        api:
            limit:
                resolver: App\RateLimit\PlanLimitResolver
            interval: '1 minute'
```

Changing the resolved limit selects a different limiter counter.

### Make the bucket dynamic

```php
use JacyImp\ApiPlatformRateLimiter\Contract\BucketResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicBucket;

final class PlanBucketResolver implements BucketResolverInterface
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

```php
new RateLimit(
    bucket: new DynamicBucket(PlanBucketResolver::class),
    limit: 1000,
    interval: '1 minute',
)
```

A dynamic bucket can also choose a configured bucket:

```yaml
api_platform_rate_limiter:
    buckets:
        free:
            limit: 100
            interval: '1 minute'

        premium:
            limit: 1000
            interval: '1 minute'
```

```php
new RateLimit(
    bucket: new DynamicBucket(PlanBucketResolver::class),
)
```

### Make request cost dynamic

```php
use JacyImp\ApiPlatformRateLimiter\Contract\CostResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicCost;

final class RequestCostResolver implements CostResolverInterface
{
    public function resolve(): int
    {
        return 10;
    }
}
```

```php
new RateLimit(
    bucket: 'catalog',
    limit: 1000,
    interval: '1 minute',
    cost: new DynamicCost(RequestCostResolver::class),
)
```

### Apply a limit only when a condition matches

```php
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

```php
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Condition;

new RateLimit(
    limit: 100,
    interval: '1 minute',
    when: new Condition(InternalRequestCondition::class),
)
```

Combine conditions:

```php
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AllOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Condition;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Not;

new RateLimit(
    limit: 100,
    interval: '1 minute',
    when: new AllOf([
        new Condition(AuthenticatedCondition::class),
        new Not(new Condition(InternalRequestCondition::class)),
    ]),
)
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

### Use a custom identity

The default identity is the authenticated user, falling back to client IP.

```php
use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;

final class ApiKeyIdentityResolver implements IdentityResolverInterface
{
    public function resolve(): ?string
    {
        return 'api-key:example';
    }
}
```

```php
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\Identity;

new RateLimit(
    limit: 100,
    interval: '1 minute',
    identity: new Identity(ApiKeyIdentityResolver::class),
)
```

Fallback chain:

```php
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\FirstAvailableIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\Identity;

new RateLimit(
    limit: 100,
    interval: '1 minute',
    identity: new FirstAvailableIdentity([
        new Identity(ApiKeyIdentityResolver::class),
        new Identity(UserIdentityResolver::class),
        new Identity(IpIdentityResolver::class),
    ]),
)
```

Composite identity:

```php
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\CompositeIdentity;

new RateLimit(
    limit: 100,
    interval: '1 minute',
    identity: new CompositeIdentity([
        new Identity(TenantIdentityResolver::class),
        new Identity(UserIdentityResolver::class),
    ]),
)
```

Replace the Symfony default globally:

```yaml
# config/services.yaml

services:
    JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface:
        alias: App\RateLimit\ApiKeyIdentityResolver
```

### Bypass rate limiting

Bypass every resolved limit on an operation/resource:

```php
use JacyImp\ApiPlatformRateLimiter\Metadata\BypassRateLimit;

extraProperties: [
    BypassRateLimit::class => new BypassRateLimit(),
]
```

Bypass one shared bucket:

```php
extraProperties: [
    BypassRateLimit::class => new BypassRateLimit(
        bucket: 'catalog',
    ),
]
```

Bypass one global:

```php
extraProperties: [
    BypassRateLimit::class => new BypassRateLimit(
        bucket: 'global:burst',
    ),
]
```

Conditional bypass:

```php
extraProperties: [
    BypassRateLimit::class => new BypassRateLimit(
        bucket: 'catalog',
        when: new Condition(InternalRequestCondition::class),
    ),
]
```

### Bypass the entire request from infrastructure code

```php
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitBypassInterface;

final class TrustedCrawlerBypass implements RateLimitBypassInterface
{
    public function shouldBypass(): bool
    {
        return true;
    }
}
```

Symfony autoconfigures implementations. Laravel registers them in the published config:

```php
'bypasses' => [
    App\RateLimit\TrustedCrawlerBypass::class,
],
```

### Build limits from arbitrary runtime state

```php
use ApiPlatform\Metadata\Operation;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitProviderInterface;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

final class SubscriptionRateLimitProvider implements RateLimitProviderInterface
{
    public function __construct(
        private SubscriptionContext $subscription,
    ) {
    }

    public function provide(Operation $operation): iterable
    {
        if (!$this->subscription->shouldRateLimit()) {
            return [];
        }

        return [
            new RateLimit(
                limit: $this->subscription->limit(),
                interval: '1 minute',
            ),
        ];
    }
}
```

Symfony autoconfigures providers. Laravel:

```php
'providers' => [
    App\RateLimit\SubscriptionRateLimitProvider::class,
],
```

## Intervals

```php
new RateLimit(limit: 100, interval: '30 seconds');
new RateLimit(limit: 100, interval: '1 minute');
new RateLimit(limit: 1000, interval: '1 hour');
```

```php
use DateInterval;
use JacyImp\ApiPlatformRateLimiter\Metadata\Interval;

new RateLimit(
    limit: 100,
    interval: new DateInterval('PT1M'),
);

new RateLimit(
    limit: 100,
    interval: new Interval(minutes: 1),
);
```

Intervals must be at least one second. Months, years, negative values, and fractional seconds are not supported.

## Storage

### Symfony

Default:

```text
cache.app
```

Dedicated cache pool:

```yaml
api_platform_rate_limiter:
    cache_pool: cache.rate_limiter
```

Custom Symfony RateLimiter storage:

```yaml
api_platform_rate_limiter:
    storage: app.rate_limit_storage
```

The storage service must implement Symfony's `StorageInterface`.

### Laravel

```php
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

Symfony:

```yaml
services:
    JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejectionHandlerInterface:
        alias: App\RateLimit\ApiRateLimitRejectionHandler
```

All package exceptions implement `RateLimiterExceptionInterface`.

## Laravel resolver registration

Symfony autoconfigures the resolver contracts used above. Laravel lists selectable resolvers in the published config:

```php
'resolvers' => [
    'identity' => [
        App\RateLimit\ApiKeyIdentityResolver::class,
    ],
    'condition' => [
        App\RateLimit\AuthenticatedCondition::class,
        App\RateLimit\InternalRequestCondition::class,
    ],
    'bucket' => [
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

## Lifecycle events

```php
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