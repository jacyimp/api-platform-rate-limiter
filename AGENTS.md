## Project Principles

This package prioritizes:

1. Clarity
2. Simplicity
3. Developer experience
4. Correctness
5. Extensibility only when justified

Do not add abstractions for hypothetical future needs. Prefer fewer concepts, fewer public types, fewer configuration steps, and conventional Symfony/API Platform behavior.

## Compatibility

Support:

- PHP 8.2+
- API Platform 3.4 / 4.x
- Symfony 6.4 / 7.x / 8.x

Always consider lowest-supported dependencies, not only the locally installed versions.

## Public API

Keep the public API intentionally small.

Expected consumer-facing types:

- `Metadata\RateLimit`
- `Metadata\SharedRateLimit`
- `Metadata\RateLimitPolicy`
- `Metadata\Interval`
- `Contract\IdentityResolverInterface`
- `Contract\RateLimitBypassInterface`
- `Symfony\ApiPlatformRateLimiterBundle`

Treat `Core`, API Platform adapters, Symfony listeners, DI classes, storage adapters, resolvers, and orchestration classes as implementation details. Mark them `@internal` where appropriate.

Do not leak internal types through public extension interfaces.

## Design Rules

Prefer obvious APIs:

    new RateLimit(
        limit: 100,
        interval: '1 minute',
    );

Human-readable interval strings are the primary API. `DateInterval` and `Interval` are advanced alternatives.

Inline rate limiting should work with no additional configuration after bundle registration.

Shared buckets may require central configuration.

Do not introduce builders, factories, interfaces, configuration objects, or framework abstractions unless they solve a real current problem.

Crawler detection and crawler verification do not belong in this package. Consumers can implement them through bypass rules.

## Architecture

Keep boundaries clear:

    API Platform metadata
        → extraction
        → resolution
        → enforcement
        → limiter/storage

Identity resolution and bypass checks are enforcement collaborators.

Do not pass API Platform `Operation` objects deep into the core.

Keep Symfony HTTP concerns in the Symfony integration layer.

Multiple limits are consumed sequentially. Consumption from an earlier successful limit is not rolled back if a later limit rejects.

## Symfony

Default identity:

1. authenticated user identifier;
2. otherwise client IP.

Use Symfony trusted-proxy handling. Never manually parse `X-Forwarded-For`.

Security integration must remain optional.

Use `cache.app` for Symfony RateLimiter storage.

Avoid requiring users to manually configure internal services.

## PHP Style

Every PHP file must contain:

    <?php

    declare(strict_types=1);

Follow PSR-12 and configured Slevomat rules.

Use:

- strict type hints;
- sorted imports;
- no unused imports;
- early returns;
- static closures where possible.

PHPStan runs at `level: max` with PHP 8.2 as baseline.

Prefer concrete intermediate variables over fragile long fluent chains when Symfony 6.4 typing requires it.

Avoid PHPStan suppressions unless narrowly justified.

## Tests

Use PHPUnit attributes.

Test methods:

- use `#[Test]`;
- start with `it...`.

Use `#[CoversClass(...)]` for unit tests with a clear SUT.

Prefer:

    self::createStub(...)
    self::createMock(...)

Use precise generic types for data providers, preferably `Generator`.

Test observable behavior rather than internal implementation details.

Kernel integration tests should exercise real Symfony wiring.

Do not weaken tests or disable risky/deprecation detection to make CI pass.

## Commands

Before completing work:

    composer check
    composer audit

For dependency-sensitive changes:

    composer update --prefer-lowest --prefer-stable --no-interaction
    composer check

Then verify current dependencies:

    composer update
    composer check

Do not commit `composer.lock`.

CI covers PHP 8.2, 8.3, 8.4, 8.5, plus PHP 8.2 with lowest dependencies.

## Documentation

Keep README onboarding minimal.

Show the simplest working example first, then advanced features.

Examples must reflect real implemented APIs.

Update README and CHANGELOG when public behavior changes.

## Scope Control

Solve the requested task and directly affected tests/docs only.

Do not prematurely add:

- weighted costs;
- atomic multi-limit consumption;
- distributed locking;
- crawler verification;
- remote IP-list fetching;
- Laravel integration;
- speculative storage abstractions;
- unnecessary factories/interfaces.

## Commits

Use Conventional Commits:

    feat: ...
    fix(symfony): ...
    refactor(api): ...
    test: ...
    docs: ...
    chore: ...

Keep commits focused.

## Decision Rule

When several designs work, choose the one with:

- fewer concepts;
- clearer names;
- smaller public API;
- less framework leakage;
- less required configuration;
- easier debugging.

Prefer a little duplication over an abstraction that makes the package harder to understand.
