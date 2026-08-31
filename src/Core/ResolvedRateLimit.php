<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Core;

use InvalidArgumentException;

/**
 * @internal
 */
final readonly class ResolvedRateLimit
{
    public function __construct(
        public string $bucket,
        public RateLimitDefinition $definition,
    ) {
        if (trim($bucket) === '') {
            throw new InvalidArgumentException(
                'Rate limit bucket cannot be empty.',
            );
        }
    }
}
