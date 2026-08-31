# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog and this project follows Semantic Versioning.

## [Unreleased]

### Changed

- Renamed operation metadata from `OperationRateLimit` to `RateLimit`.
- Simplified bypass rules to `shouldBypass(): bool`.
- Moved `RateLimiterInterface` into `Core` and marked implementation types as internal.
- Documented sequential combined-limit consumption without rollback.

### Added

- Optional global rate-limit configuration shared by every API Platform
  operation.
- Support for defining `RateLimit` and `SharedRateLimit` metadata on an
  `ApiResource`, applying it to all of the resource's operations.
- A package-specific exception hierarchy with `RateLimiterExceptionInterface`
  as its common catch point and specific validation, identity, shared-bucket,
  metadata, and rate-limit rejection exceptions.
- Immutable PSR-14 `RateLimitChecking`, `RateLimitConsumed`, and
  `RateLimitRejected` lifecycle events.
- A replaceable `RateLimitRejectionHandlerInterface` for customizing exception
  handling when a request exceeds its limit.
- Per-limit identity resolvers and positive `when` conditions for operation and
  shared limits.
- Autoconfiguration and explicit service tags for selectable per-limit
  strategies.

## [0.1.0] - 2026-08-31

### Added

- Operation-specific API Platform rate limits.
- Shared rate-limit buckets.
- Fixed-window and sliding-window policies.
- Human-readable, `DateInterval`, and value-object interval support.
- Symfony RateLimiter adapter.
- Authenticated-user and client-IP identity resolution.
- Custom identity resolver support.
- Extensible rate-limit bypass rules.
- Symfony dependency-injection integration.
- Central Symfony configuration for shared buckets.
- `429 Too Many Requests` responses with retry and rate-limit headers.
- Unit and Symfony kernel integration test coverage.
- PHP 8.2 through PHP 8.5 compatibility testing.
- Lowest-supported-dependency CI coverage.
