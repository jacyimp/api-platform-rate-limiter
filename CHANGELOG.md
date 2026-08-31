# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog and this project follows Semantic Versioning.

## [Unreleased]

### Changed

- Formalized persisted counter identity as the collision-safe tuple of final
  bucket, resolved identity, policy, limit, and normalized interval. Dynamic
  definition changes select separate state, while request cost does not.
- Renamed `DynamicCostResolverInterface` to `CostResolverInterface`, matching
  `BucketResolverInterface` and `LimitResolverInterface`.
- Reordered advanced `RateLimit` constructor options to `bucket`, `cost`,
  `identity`, `when`, and `policy` after the common `limit`/`interval` pair.
- Replaced YAML `limit_resolver`, `bucket_resolver`, and `cost_resolver` options
  with resolver mappings under `limit`, `bucket`, and `cost`; replaced
  `identity_resolver` with `identity`.
- Routed named globals through the same declaration-resolution pipeline as
  operation and resource limits, including dynamic values, identities,
  conditions, costs, buckets, and compatible shared-definition lookup.
- Replaced `RateLimit::$identityResolver` with composable `identity` metadata;
  identity resolvers may now return `null` when unavailable.
- Replaced string `when` metadata with composable condition expressions and
  renamed runtime condition checks from `shouldApply()` to `matches()`.
- Replaced the singular `global` configuration with named `globals`; every
  configured global is enforced independently using a `global:<name>` bucket.
- Merged the unreleased `SharedRateLimit` metadata API into `RateLimit` via its
  optional `bucket`; combined metadata now accepts a list of `RateLimit` values.
- Renamed operation metadata from `OperationRateLimit` to `RateLimit`.
- Simplified bypass rules to `shouldBypass(): bool`.
- Moved `RateLimiterInterface` into `Core` and marked implementation types as internal.
- Documented sequential combined-limit consumption without rollback.
- Defined providers as an additive source of `RateLimit` declarations resolved
  after operation/resource metadata and before globals. Provider declarations
  now have explicitly documented common resolution, bypass, ordering, error,
  and non-deduplication semantics.

### Added

- First-class Laravel 11-13 + API Platform integration with package discovery,
  operation middleware, Laravel identity and rejection adapters, publishable
  configuration, isolated cache storage, and Laravel event dispatching.
- A small framework-neutral configuration factory for turning plain framework
  configuration into the existing metadata model.
- `Identity`, `CompositeIdentity`, and `FirstAvailableIdentity` expressions,
  including nested composition and collision-safe composite encoding.
- `Condition`, `AllOf`, `AnyOf`, and `Not` expressions for composing nested
  rate-limit and bypass conditions with short-circuit evaluation.
- Declarative `BypassRateLimit` resource and operation metadata, with optional
  resolved-bucket matching and conditions.
- Weighted token consumption through the per-limit `cost` option, including
  dynamic costs resolved by `CostResolverInterface` services.
- Dynamic bucket and limit metadata through `DynamicBucket`, `DynamicLimit`,
  `BucketResolverInterface`, and `LimitResolverInterface`.
- Optional global rate-limit configuration shared by every API Platform
  operation.
- Support for defining `RateLimit` metadata on an `ApiResource`, applying it to
  all of the resource's operations.
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
