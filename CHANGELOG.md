# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog and this project follows Semantic Versioning.

## [Unreleased]

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
