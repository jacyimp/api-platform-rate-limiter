# TODO

## 1. Make Behat meaningful

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

## 2. Reach 100% code coverage

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

## 3. Add Infection with 100% MSI

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

## 4. CI hardening

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

## 5. Documentation hardening

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

6. Make sure RateLimit and SharedRateLimit can be defined on ApiResource level (and not just operation level)
7. Make users be able to disable the ratelimit altogether for testing/development
8. Add a global ratelimit config
9. Make sure the global ratelimit can be excluded operation/resource level
10. Make sure users can define custom exceptions for their ratelimiting
11. Make sure users can use different custom caches
