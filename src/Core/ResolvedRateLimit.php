<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Core;

use InvalidArgumentException;
use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;

/**
 * @internal
 */
final readonly class ResolvedRateLimit
{
    public function __construct(
        public string $bucket,
        public RateLimitDefinition $definition,
        public ?IdentityResolverInterface $identityResolver = null,
        public ?RateLimitConditionInterface $condition = null,
    ) {
        if (trim($bucket) === '') {
            throw new InvalidArgumentException(
                'Rate limit bucket cannot be empty.',
            );
        }
    }
}
