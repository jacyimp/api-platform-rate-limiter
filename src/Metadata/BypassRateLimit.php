<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;

final readonly class BypassRateLimit
{
    public function __construct(
        public ?string $bucket = null,
        public ?string $when = null,
    ) {
        if ($bucket !== null && trim($bucket) === '') {
            throw new InvalidRateLimitException(
                'Bypass rate limit bucket cannot be empty.',
            );
        }

        if ($when !== null && trim($when) === '') {
            throw new InvalidRateLimitException(
                'Bypass rate limit condition service ID cannot be empty.',
            );
        }
    }
}
