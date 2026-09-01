# Conditional limits and bypasses

Use conditions when a quota should exist only for matching requests. Use bypass metadata to exempt an operation from selected resolved limits, and `RateLimitBypassInterface` for trusted application-wide exemptions.

## Apply a limit conditionally

Implement `RateLimitConditionInterface` with request-specific logic.

Symfony:

```php
<?php

namespace App\RateLimit;

use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class AuthenticatedCondition implements RateLimitConditionInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function matches(): bool
    {
        return $this->tokenStorage->getToken()?->getUser()
            instanceof UserInterface;
    }
}
```

Laravel:

```php
<?php

namespace App\RateLimit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;

final readonly class AuthenticatedCondition implements RateLimitConditionInterface
{
    public function __construct(
        private Request $request,
    ) {
    }

    public function matches(): bool
    {
        return $this->request->user() instanceof Authenticatable;
    }
}
```

Reference the condition with `Condition`:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\RateLimit\AuthenticatedCondition;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Condition;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new GetCollection(
            extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 200,
                    interval: '1 minute',
                    when: new Condition(AuthenticatedCondition::class),
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

If `matches()` returns `false`, that declaration is not enforced.

## Compose conditions

This condition matches trusted internal traffic marked by earlier application middleware.

Symfony:

```php
<?php

namespace App\RateLimit;

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
            ?->attributes
            ->getBoolean('trusted_internal');
    }
}
```

Laravel:

```php
<?php

namespace App\RateLimit;

use Illuminate\Http\Request;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;

final readonly class InternalRequestCondition implements RateLimitConditionInterface
{
    public function __construct(
        private Request $request,
    ) {
    }

    public function matches(): bool
    {
        return $this->request->attributes->getBoolean('trusted_internal');
    }
}
```

Use `AllOf`, `AnyOf`, and `Not` to compose services. This example gives guests 20 requests/minute and limits authenticated, non-internal callers to 200 requests/minute:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\RateLimit\AuthenticatedCondition;
use App\RateLimit\InternalRequestCondition;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AllOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Condition;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Not;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new GetCollection(
            extraProperties: [
                RateLimit::class => [
                    new RateLimit(
                        limit: 20,
                        interval: '1 minute',
                        when: new Not(
                            new Condition(AuthenticatedCondition::class),
                        ),
                    ),
                    new RateLimit(
                        limit: 200,
                        interval: '1 minute',
                        when: new AllOf([
                            new Condition(AuthenticatedCondition::class),
                            new Not(
                                new Condition(InternalRequestCondition::class),
                            ),
                        ]),
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

Use `AnyOf` when either condition is sufficient:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\RateLimit\AuthenticatedCondition;
use App\RateLimit\InternalRequestCondition;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AnyOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Condition;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

#[ApiResource(
    operations: [
        new GetCollection(
            extraProperties: [
                RateLimit::class => new RateLimit(
                    limit: 500,
                    interval: '1 minute',
                    when: new AnyOf([
                        new Condition(AuthenticatedCondition::class),
                        new Condition(InternalRequestCondition::class),
                    ]),
                ),
            ],
        ),
    ],
)]
final class AuditLog
{
    // ...
}
```

Configured conditions use the same expression tree.

Symfony:

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

Laravel:

```php
'globals' => [
    'public_api' => [
        'limit' => 100,
        'interval' => '1 minute',
        'when' => [
            'all_of' => [
                App\RateLimit\AuthenticatedCondition::class,
                ['not' => App\RateLimit\InternalRequestCondition::class],
            ],
        ],
    ],
],
```

## Bypass all limits on an operation or resource

Add `BypassRateLimit` without a bucket to exempt the operation from local, shared, provider, and global limits:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use JacyImp\ApiPlatformRateLimiter\Metadata\BypassRateLimit;

#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(
            extraProperties: [
                BypassRateLimit::class => new BypassRateLimit(),
            ],
        ),
    ],
)]
final class HealthCheck
{
    // ...
}
```

Put the same metadata on the resource to exempt all its operations:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use JacyImp\ApiPlatformRateLimiter\Metadata\BypassRateLimit;

#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
    ],
    extraProperties: [
        BypassRateLimit::class => new BypassRateLimit(),
    ],
)]
final class PublicStatus
{
    // ...
}
```

## Bypass one shared bucket or global

Name a [configured shared bucket](quotas.md#configured-shared-buckets) to bypass only that quota:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use JacyImp\ApiPlatformRateLimiter\Metadata\BypassRateLimit;

#[ApiResource(
    operations: [
        new Get(
            extraProperties: [
                BypassRateLimit::class => new BypassRateLimit(
                    bucket: 'catalog',
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

Configured globals use the `global:<name>` namespace. For example, the `burst` [global from the quota guide](quotas.md#limit-the-whole-api) is addressed as `global:burst`:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use JacyImp\ApiPlatformRateLimiter\Metadata\BypassRateLimit;

#[ApiResource(
    operations: [
        new Get(
            extraProperties: [
                BypassRateLimit::class => new BypassRateLimit(
                    bucket: 'global:burst',
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

Other local, shared, and global limits continue to apply.

## Bypass conditionally

Attach a condition to a bypass. This uses `InternalRequestCondition` defined above:

```php
<?php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\RateLimit\InternalRequestCondition;
use JacyImp\ApiPlatformRateLimiter\Metadata\BypassRateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Condition;

#[ApiResource(
    operations: [
        new Get(
            extraProperties: [
                BypassRateLimit::class => new BypassRateLimit(
                    bucket: 'catalog',
                    when: new Condition(InternalRequestCondition::class),
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

## Bypass trusted internal traffic globally

Implement `RateLimitBypassInterface` for a request-wide exemption. The package calls registered bypasses before consuming each resolved limit.

Symfony:

```php
<?php

namespace App\RateLimit;

use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitBypassInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class InternalTrafficBypass implements RateLimitBypassInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function shouldBypass(): bool
    {
        return $this->requestStack
            ->getCurrentRequest()
            ?->attributes
            ->getBoolean('trusted_internal');
    }
}
```

Laravel:

```php
<?php

namespace App\RateLimit;

use Illuminate\Http\Request;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitBypassInterface;

final readonly class InternalTrafficBypass implements RateLimitBypassInterface
{
    public function __construct(
        private Request $request,
    ) {
    }

    public function shouldBypass(): bool
    {
        return $this->request->attributes->getBoolean('trusted_internal');
    }
}
```

Only trusted middleware should set this attribute after authenticating the caller. Never bypass from a client-controlled header alone.

Symfony autoconfigures the bypass. Register it explicitly on Laravel:

```php
'bypasses' => [
    App\RateLimit\InternalTrafficBypass::class,
],
```

## Bypass verified crawlers

Crawler detection and verification belong in application or edge infrastructure. After that code verifies a crawler, expose the result as a trusted request attribute and keep the package integration small.

Symfony:

```php
<?php

namespace App\RateLimit;

use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitBypassInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class VerifiedCrawlerBypass implements RateLimitBypassInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function shouldBypass(): bool
    {
        return $this->requestStack
            ->getCurrentRequest()
            ?->attributes
            ->getBoolean('verified_crawler');
    }
}
```

Laravel:

```php
<?php

namespace App\RateLimit;

use Illuminate\Http\Request;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitBypassInterface;

final readonly class VerifiedCrawlerBypass implements RateLimitBypassInterface
{
    public function __construct(
        private Request $request,
    ) {
    }

    public function shouldBypass(): bool
    {
        return $this->request->attributes->getBoolean('verified_crawler');
    }
}
```

Set `verified_crawler` only after your verifier has validated the crawler through its documented mechanism, such as signed edge metadata or verified DNS. A user-agent string is not verification.

Symfony autoconfigures this implementation. Laravel registration:

```php
'bypasses' => [
    App\RateLimit\InternalTrafficBypass::class,
    App\RateLimit\VerifiedCrawlerBypass::class,
],
```

## Framework registration

Symfony autoconfigures `RateLimitConditionInterface` and `RateLimitBypassInterface` implementations.

Laravel condition services must also be selectable in the published config:

```php
'resolvers' => [
    // ...
    'condition' => [
        App\RateLimit\AuthenticatedCondition::class,
        App\RateLimit\InternalRequestCondition::class,
    ],
],
```

## See also

- [Choosing who gets rate limited](identities.md)
- [Quotas and shared limits](quotas.md)
- [Extending the rate limiter](extending.md)
- [README](../README.md)
