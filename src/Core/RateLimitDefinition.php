<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Core;

use InvalidArgumentException;
use Jacyimp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;

final readonly class RateLimitDefinition
{
    public function __construct(
        public int $limit,
        public int $intervalSeconds,
        public RateLimitPolicy $policy,
    ) {
        if ($limit < 1) {
            throw new InvalidArgumentException(
                'Rate limit must be greater than zero.',
            );
        }

        if ($intervalSeconds < 1) {
            throw new InvalidArgumentException(
                'Rate limit interval must be greater than zero.',
            );
        }
    }
}
