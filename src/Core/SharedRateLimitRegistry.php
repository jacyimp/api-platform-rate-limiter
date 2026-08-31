<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Core;

use InvalidArgumentException;

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
            ?? throw new InvalidArgumentException(
                sprintf(
                    'Shared rate limit bucket "%s" is not defined.',
                    $bucket,
                ),
            );
    }
}
