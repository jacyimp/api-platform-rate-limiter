<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Core;

use JacyImp\ApiPlatformRateLimiter\Exception\UndefinedSharedBucketException;

/**
 * @internal
 */
final readonly class SharedRateLimitRegistry
{
    /**
     * @param array<string, RateLimitDefinition> $definitions
     */
    public function __construct(
        private array $definitions,
    ) {
    }

    public function get(string $bucket): RateLimitDefinition
    {
        return $this->definitions[$bucket]
            ?? throw new UndefinedSharedBucketException(
                sprintf(
                    'Shared rate limit bucket "%s" is not defined.',
                    $bucket,
                ),
            );
    }
}
