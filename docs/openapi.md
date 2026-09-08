# Swagger / OpenAPI

Swagger UI / OpenAPI operation descriptions automatically include a **Rate limits** section with the quota, interval, policy, and request cost. Existing descriptions are preserved. No additional configuration is needed when API Platform OpenAPI support is installed.

Resource and operation declarations, configured buckets, and global quotas are included. Unconditional metadata bypasses omit matching limits.

Dynamic values and conditional limits are labeled without running request-dependent resolvers. Limits supplied by runtime providers are not included in generated documentation.
