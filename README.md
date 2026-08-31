# API Platform Rate Limiter

> Work in progress — not ready for production use.

Extensible rate limiting for API Platform applications.

The goal of this package is to provide a simple, API Platform-native way to apply rate limits to individual operations and shared buckets, while keeping identity resolution, bypass rules, and framework-specific behavior extensible.

## Planned Features

* Operation-specific rate limits
* Shared rate-limit buckets
* Symfony RateLimiter integration
* Authenticated user / client IP identification
* Custom identity resolvers
* Custom rate-limit bypass rules
* Proper `429 Too Many Requests` responses and rate-limit headers
* Support for API Platform 3+

Laravel integration may be added later. The core package is being designed to avoid unnecessary coupling to Symfony.

## Requirements

* PHP 8.2+
* API Platform 3+

## Installation

Not available on Packagist yet.

Development is currently in progress.

## Example API

The final API is still being designed, but usage is expected to look roughly like:

```php
#[RateLimit(
    limit: 100,
    interval: '1 minute',
)]
```

For limits shared by multiple operations:

```php
#[SharedRateLimit('catalog')]
```

Shared buckets will be configured centrally.

## Status

🚧 Early development.

Public APIs may change substantially before the first stable release.

## License

MIT
