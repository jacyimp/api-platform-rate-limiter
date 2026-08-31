# TODO

## 1. Per-limit identity resolvers and bypass rules

Allow each individual rate limit to override the global identity resolver and bypass behavior.

### Operation limits

Extend `RateLimit` so an operation can select:

- a custom identity resolver;
- a custom bypass rule.

Example use cases:

- OTP endpoint keyed by `phone_number` from the request body;
- login endpoint keyed by username or email;
- API-key-specific limits;
- crawler-specific bypass behavior;
- endpoint-specific trusted-client bypasses.

Global identity resolution and bypass behavior remain the defaults.

A per-limit configuration only overrides the behavior for that specific limit.

### Shared limits

Allow shared buckets to configure their own:

- identity resolver;
- bypass rule.

Example:

```yaml
api_platform_rate_limiter:
    shared_buckets:
        otp:
            limit: 5
            interval: '1 minute'
            identity_resolver: App\RateLimit\PhoneNumberIdentityResolver
            bypass: App\RateLimit\InternalOtpBypass
```

Decide whether these values should be:

* service IDs;
* named strategies;
* dedicated metadata/value objects.

Prefer the simplest Symfony-native approach.

### Enforcement semantics

Each resolved limit may have its own identity and bypass behavior.

For multiple limits on the same operation:

```text
operation limit
    → resolve its identity
    → evaluate its bypass
    → consume if applicable

shared limit
    → resolve its identity
    → evaluate its bypass
    → consume if applicable
```

Do not assume all limits on one request use the same identity.

Define precedence explicitly:

```text
per-limit resolver/bypass
    ↓
global resolver/bypass
```

Preserve the existing global configuration as the zero-configuration default.

### DI

Support:

* Symfony autoconfiguration;
* explicit/manual service configuration;
* explicit service tags where applicable;
* applications with `autowire: false`;
* applications with `autoconfigure: false`.

Do not expose internal `Core` types through the public contracts.

---

## 2. Complete public API and extension-point audit

Review the package as if consuming it from an unrelated application.

The public API should be small, stable, and sufficient for realistic integrations.

Review:

* `RateLimit`;
* `SharedRateLimit`;
* `RateLimitPolicy`;
* `Interval`;
* `IdentityResolverInterface`;
* `RateLimitBypassInterface`;
* `RateLimitProviderInterface`;
* public events;
* Symfony bundle;
* public service tags;
* service aliases;
* Symfony service decoration possibilities.

Verify that consumers can:

* customize identity resolution;
* bypass limits;
* dynamically provide rate limits;
* manually register integrations through DI;
* decorate intended services where appropriate;
* observe rate-limiter activity;
* use the library without depending on internal classes.

Treat these as implementation details unless a strong reason appears otherwise:

* `Core`;
* API Platform adapters;
* listeners;
* storage adapters;
* resolver internals;
* DI implementation classes.

Avoid adding hooks only because they might theoretically be useful.

---

## 3. Add observational lifecycle events

Add a small event surface for logging, metrics, tracing, auditing, and other AOP-style integrations.

Use PSR-14 rather than introducing a package-specific event dispatcher abstraction.

Add:

```text
RateLimitChecking
RateLimitConsumed
RateLimitRejected
```

Events must:

* be immutable;
* expose only public-safe data;
* not expose `Core\ResolvedRateLimit` or other internal types;
* not allow subscribers to alter enforcement behavior;
* work with Symfony's event dispatcher.

Behavior-changing customization belongs in explicit contracts, not events.

Document how Symfony users subscribe to these events.

---

## 4. Package-specific exception hierarchy

Replace generic exceptions created throughout the package with explicit package exceptions.

Introduce a common marker:

```text
RateLimiterExceptionInterface
```

Candidate exceptions:

```text
InvalidRateLimitException
InvalidIntervalException
InvalidRateLimitMetadataException
UndefinedSharedBucketException
IdentityResolutionException
InvalidRateLimitProviderException
RateLimitExceededException
```

Use appropriate inheritance where useful.

Examples:

```text
InvalidIntervalException
    extends InvalidArgumentException

RateLimitExceededException
    extends TooManyRequestsHttpException
```

Goals:

* consumers can catch all package exceptions through one interface;
* specific failures remain individually catchable;
* error messages remain clear;
* no generic ad-hoc `RuntimeException` / `InvalidArgumentException` where a package-specific type is appropriate.

Review every `throw` in `src/`.

---

## 5. Make Behat meaningful

Behat currently exists in the project but should prove actual consumer-facing behavior.

Add feature scenarios for:

* operation-specific rate limits;
* shared buckets;
* combined limits;
* rate-limit rejection;
* separate identities;
* custom identity resolvers;
* custom bypass rules;
* dynamic `RateLimitProviderInterface`;
* explicitly tagged/manual DI services;
* per-limit identity resolution;
* per-limit bypass behavior.

Prefer scenarios that exercise the package through a real Symfony kernel.

Do not duplicate low-level unit tests in Gherkin.

Use PHPUnit for implementation edge cases and Behat for externally observable package behavior.

---

## 6. Reach 100% code coverage

Enforce 100% line coverage for package source code.

Coverage scope:

```text
src/
```

Add a dedicated Composer command, for example:

```text
composer coverage
```

Generate machine-readable coverage output for CI.

Do not run coverage for every PHP compatibility matrix entry.

Use one dedicated quality job with PCOV or Xdebug.

CI must fail if source line coverage drops below 100%.

Do not achieve 100% by excluding legitimate production code.

---

## 7. Add Infection with 100% MSI

Add Infection mutation testing.

Target:

```text
src/
```

Require:

```text
MSI >= 100%
Covered MSI >= 100%
```

Add a Composer command such as:

```text
composer mutation
```

Run Infection in a dedicated CI quality job.

Do not run Infection on every supported PHP version.

Avoid broad ignored-mutator lists or directory exclusions.

If a genuinely equivalent mutant cannot be killed:

* document why;
* exclude it narrowly;
* keep such exceptions rare.

The goal is meaningful mutation resistance, not a cosmetic score.

---

## 8. CI hardening

Separate CI responsibilities.

### Compatibility

Run:

```text
PHP 8.2
PHP 8.3
PHP 8.4
PHP 8.5
```

Each runs:

```text
composer check
```

### Lowest dependencies

Run PHP 8.2 with:

```bash
composer update --prefer-lowest --prefer-stable --no-interaction
composer check
```

### Quality

Run one dedicated job for:

```text
composer audit
100% code coverage
100% Infection MSI
```

Keep CI strict on:

* warnings;
* risky tests;
* deprecations;
* PHPStan max;
* PHPCS;
* Behat;
* PHPUnit.

---

## 9. Documentation hardening

Update README after the public API stabilizes.

Document:

* simplest operation-specific usage first;
* shared buckets;
* combined limits;
* identity resolution;
* bypass behavior;
* per-limit resolvers and bypasses;
* dynamic rate-limit providers;
* manual service tags;
* public events;
* exception hierarchy;
* multi-instance cache requirements;
* sequential multi-limit consumption behavior.

Keep advanced customization below the basic quick-start.

Do not document internal classes as supported extension points.

---

## 10. Final pre-0.1.0 audit

Before tagging:

```bash
composer update --prefer-lowest --prefer-stable --no-interaction
composer check
composer audit

composer update
composer check
composer audit

composer coverage
composer mutation
```

Then test the package from a clean external Symfony/API Platform application.

Verify:

* Packagist installation;
* bundle registration;
* inline rate limit;
* shared bucket;
* manual DI;
* custom provider;
* custom identity resolver;
* custom bypass;
* per-limit identity resolver;
* per-limit bypass;
* 429 response behavior.

Review the complete public API one final time.

Only then tag:

```text
v0.1.0
```

````

Commit:

```text
docs: add pre-release hardening roadmap
````
