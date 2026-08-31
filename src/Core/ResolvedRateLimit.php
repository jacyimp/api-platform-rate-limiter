<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Core;

use InvalidArgumentException;

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
