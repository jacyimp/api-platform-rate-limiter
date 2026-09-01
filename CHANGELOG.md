# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog and this project follows Semantic Versioning.

## [Unreleased]

### Fixed

- Returned Laravel rejection responses directly so API Platform's exception renderer cannot convert valid 429 responses into 500 errors.
- Rebuilt request-sensitive Laravel resolver, provider, bypass, and enforcement services per middleware resolution to prevent stale request state in long-lived applications and multi-request tests.

## [0.1.0] - 2026-09-01

First public release.

### Added

- Added API Platform rate limits for individual operations and every operation on a resource.
- Added multiple limits on one operation, consumed sequentially without rolling back an earlier limit when a later limit rejects the request.
- Added named global API quotas that can be combined with resource and operation limits.
- Added shared rate-limit buckets with inline definitions or central Symfony and Laravel configuration.
- Added fixed-window and sliding-window policies with human-readable intervals, `DateInterval`, and the package `Interval` value object.
- Added weighted request costs so expensive operations can consume more capacity from a quota.
- Added dynamic quotas with runtime-resolved limits, shared buckets, and request costs through `DynamicLimit`, `DynamicBucket`, `DynamicCost`, `LimitResolverInterface`, `BucketResolverInterface`, and `CostResolverInterface`.
- Added authenticated-user identity resolution with a client-IP fallback by default, using each host framework's trusted-proxy-aware request handling.
- Added composable custom identities with resolver-backed values, fallback chains, and combined identities through `Identity`, `FirstAvailableIdentity`, and `CompositeIdentity`.
- Added conditional rate limiting with composable `Condition`, `AllOf`, `AnyOf`, and `Not` expressions.
- Added declarative resource- and operation-level exemptions through `BypassRateLimit`, plus request-wide infrastructure bypasses through `RateLimitBypassInterface`.
- Added runtime rate-limit declarations through `RateLimitProviderInterface` and custom rejection handling through `RateLimitRejectionHandlerInterface`.
- Added default `429 Too Many Requests` rejection responses with `Retry-After`, `RateLimit-Limit`, and `RateLimit-Remaining` headers.
- Added immutable PSR-14 lifecycle events for rate-limit checking, successful consumption, and rejection.
- Added Symfony bundle integration with dependency-injection autoconfiguration, inline limits that require no package configuration, configurable buckets and globals, and isolated cache-backed storage.
- Added Laravel 11–13 integration with API Platform for Laravel, including package discovery, operation middleware, framework-native identity and rejection handling, isolated cache storage, and publishable configuration.
- Added custom storage support and shared cache-backed counters for multi-instance deployments.
- Added a package exception hierarchy with `RateLimiterExceptionInterface` as the common catch point.
- Added support for PHP 8.2 through PHP 8.5, API Platform 3.4 and 4.x, and Symfony 6.4, 7.x, and 8.x.
- Added automated quality gates covering PHPStan at maximum level, coding standards, PHPUnit, Behat behavior tests, 100% source line coverage, 100% Infection MSI and covered MSI, lowest-supported dependencies, Symfony/API Platform compatibility combinations, and Laravel 11–13 integration with API Platform for Laravel.

### Fixed

- Normalized invalid human-readable intervals to `InvalidIntervalException` consistently across supported PHP versions.
