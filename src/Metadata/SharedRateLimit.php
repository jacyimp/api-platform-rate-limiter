<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;

final readonly class SharedRateLimit
{
    public function __construct(
        public string $bucket,
    ) {
        if (trim($bucket) === '') {
            throw new InvalidRateLimitException(
                'Shared rate limit bucket cannot be empty.',
            );
        }
    }
}
