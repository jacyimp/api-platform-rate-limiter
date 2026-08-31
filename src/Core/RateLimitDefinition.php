<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Core;

use InvalidArgumentException;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;

/**
 * @internal
 */
final readonly class RateLimitDefinition
{
    public function __construct(
        public int $limit,
        public int $intervalSeconds,
        public RateLimitPolicy $policy,
        public ?string $identityResolver = null,
        public ?string $when = null,
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
