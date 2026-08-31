<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Metadata;

use InvalidArgumentException;

final readonly class SharedRateLimit
{
    public function __construct(
        public string $bucket,
    ) {
        if (trim($bucket) === '') {
            throw new InvalidArgumentException(
                'Shared rate limit bucket cannot be empty.',
            );
        }
    }
}
