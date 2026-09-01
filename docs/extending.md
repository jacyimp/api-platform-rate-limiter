# Extending the rate limiter

Use the public contracts when metadata and configuration cannot express a current application requirement. Framework listeners, middleware, storage adapters, and core orchestration classes are internal implementation details.

## Add limits from application state

`RateLimitProviderInterface` adds declarations after API Platform metadata has been read. Providers receive the current API Platform `Operation`.

This provider adds an hourly quota only to the named export operation:

```php
<?php

namespace App\RateLimit;

use ApiPlatform\Metadata\Operation;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitProviderInterface;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

final class ExportRateLimitProvider implements RateLimitProviderInterface
{
    public function provide(Operation $operation): iterable
    {
        if ($operation->getName() !== 'product_export') {
            return [];
        }

        return [
            new RateLimit(
                limit: 20,
                interval: '1 hour',
            ),
        ];
    }
}
```

Name the API Platform operation that the provider selects:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;

#[ApiResource(
    operations: [
        new GetCollection(name: 'product_list'),
        new GetCollection(
            uriTemplate: '/products/export',
            name: 'product_export',
        ),
    ],
)]
final class Product
{
    // ...
}
```

Symfony autoconfigures the provider. Laravel registration:

```php
<?php

// config/api-platform-rate-limiter.php

return [
    // ...
    'providers' => [
        App\RateLimit\ExportRateLimitProvider::class,
    ],
];
```

Metadata declarations are resolved first, provider declarations next, and configured globals last. Providers are additive; returning a matching declaration does not replace metadata.

For a dynamic limit, bucket, or cost inside otherwise ordinary metadata, prefer [`DynamicLimit`, `DynamicBucket`, or `DynamicCost`](plans-and-tenants.md) over a provider.

## Application-wide bypass services

`RateLimitBypassInterface` skips every resolved limit for a request when any registered implementation returns `true`. It is suited to trusted infrastructure decisions that apply across the API.

Use `BypassRateLimit` metadata when an exemption belongs to a particular operation, resource, shared bucket, or global. See [Conditional limits and bypasses](conditions-and-bypasses.md#bypass-trusted-internal-traffic-globally) for complete Symfony and Laravel implementations and registration.

Bypass services are checked before each limit is consumed. Keep them side-effect free and return the same result throughout a request.

## Lifecycle events

The package dispatches immutable PSR-14 event objects:

- `RateLimitChecking` immediately before consumption;
- `RateLimitConsumed` after accepted consumption;
- `RateLimitRejected` after rejected consumption.

Record rejected requests with one listener class:

```php
<?php

namespace App\EventListener;

use JacyImp\ApiPlatformRateLimiter\Event\RateLimitRejected;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class RecordRateLimitRejection
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RateLimitRejected $event): void
    {
        $this->logger->warning('API rate limit rejected a request.', [
            'bucket' => $event->bucket,
            'identity' => $event->identity,
            'limit' => $event->limit,
            'interval_seconds' => $event->intervalSeconds,
            'policy' => $event->policy->value,
            'remaining' => $event->remaining,
            'retry_after' => $event->retryAfter->format(DATE_ATOM),
        ]);
    }
}
```

Symfony discovers the `AsEventListener` attribute when the application uses normal service autoconfiguration.

On Laravel, register the same invokable listener through the event facade:

```php
<?php

namespace App\Providers;

use App\EventListener\RecordRateLimitRejection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use JacyImp\ApiPlatformRateLimiter\Event\RateLimitRejected;

final class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(
            RateLimitRejected::class,
            RecordRateLimitRejection::class,
        );
    }
}
```

Laravel receives the same three event classes through Laravel's event dispatcher.

## Symfony autoconfiguration

With standard Symfony service discovery, implementations of these contracts are tagged automatically:

```text
IdentityResolverInterface
RateLimitConditionInterface
BucketResolverInterface
LimitResolverInterface
CostResolverInterface
RateLimitProviderInterface
RateLimitBypassInterface
```

The class name can then be used directly in `Identity`, `Condition`, `DynamicBucket`, `DynamicLimit`, or `DynamicCost` metadata.

The rejection handler is replaceable through its service alias rather than this selectable-strategy autoconfiguration. See [Custom rejection handler](deployment.md#custom-rejection-handler).

## Laravel registration

Laravel resolves providers, bypasses, and selectable strategies from the published package config:

```php
<?php

// config/api-platform-rate-limiter.php

return [
    // ...
    'providers' => [
        App\RateLimit\ExportRateLimitProvider::class,
    ],
    'bypasses' => [
        App\RateLimit\InternalTrafficBypass::class,
    ],
    'resolvers' => [
        'identity' => [
            App\RateLimit\ApiKeyIdentityResolver::class,
            App\RateLimit\UserIdentityResolver::class,
            App\RateLimit\IpIdentityResolver::class,
        ],
        'condition' => [
            App\RateLimit\AuthenticatedCondition::class,
            App\RateLimit\InternalRequestCondition::class,
        ],
        'bucket' => [
            App\RateLimit\TenantBucketResolver::class,
            App\RateLimit\PlanBucketResolver::class,
        ],
        'limit' => [
            App\RateLimit\PlanLimitResolver::class,
        ],
        'cost' => [
            App\RateLimit\SearchCostResolver::class,
        ],
    ],
];
```

The implementations in this complete configuration are defined in [Choosing who gets rate limited](identities.md), [Plans, tenants, and dynamic quotas](plans-and-tenants.md), and [Conditional limits and bypasses](conditions-and-bypasses.md).

## Counter behavior

The counter identity consists of:

```text
bucket + identity + policy + limit + interval
```

Consequences:

- changing a dynamic limit selects a different counter;
- changing the window policy or interval selects a different counter;
- changing `cost` does not select a different counter—it changes token consumption;
- two declarations share state only when all counter-identity components match.

Counter keys are hashed before storage. Lifecycle events expose the resolved bucket and identity, so identity resolvers should still avoid returning secrets such as raw API keys.

## Bucket namespaces

Resolved buckets are namespaced by their source:

```text
operation:<operation key>
shared:<bucket>
global:<global name>
global:<global name>:<resolved bucket>
```

The package uses these namespaces to keep an operation-local `catalog`, a shared `catalog`, and a global named `catalog` independent.

For metadata bypasses, a configured shared bucket can be addressed by its declared name such as `catalog`; a configured global uses `global:<name>`, such as `global:burst`. A dynamic global bucket uses its final name, for example `global:api:premium`.

## Multiple-limit consumption

Limits are consumed sequentially in this order:

```text
operation/resource metadata
provider declarations
configured globals
```

Consumption from an earlier accepted limit is not rolled back when a later limit rejects. Put cheap or narrow checks first when declaration order is under your control.

## See also

- [Plans, tenants, and dynamic quotas](plans-and-tenants.md)
- [Conditional limits and bypasses](conditions-and-bypasses.md)
- [Storage and production deployment](deployment.md)
- [README](../README.md)
