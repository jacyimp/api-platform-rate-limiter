<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Core;

use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;

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
            throw new InvalidRateLimitException(
                'Rate limit bucket cannot be empty.',
            );
        }
    }
}
