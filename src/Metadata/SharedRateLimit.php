<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;

final readonly class SharedRateLimit
{
    public function __construct(
        public string $bucket,
        public ?string $identityResolver = null,
        public ?string $when = null,
    ) {
        if (trim($bucket) === '') {
            throw new InvalidRateLimitException(
                'Shared rate limit bucket cannot be empty.',
            );
        }

        if ($identityResolver !== null && trim($identityResolver) === '') {
            throw new InvalidRateLimitException(
                'Identity resolver service ID cannot be empty.',
            );
        }

        if ($when !== null && trim($when) === '') {
            throw new InvalidRateLimitException(
                'Rate limit condition service ID cannot be empty.',
            );
        }
    }
}
