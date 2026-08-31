<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Core;

use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\RateLimitCondition;

/**
 * @internal
 */
final readonly class ResolvedRateLimit
{
    public function __construct(
        public string $bucket,
        public RateLimitDefinition $definition,
        public ?IdentityResolverInterface $identityResolver = null,
        public RateLimitCondition|RateLimitConditionInterface|null $condition = null,
        public int $cost = 1,
    ) {
        if (trim($bucket) === '') {
            throw new InvalidRateLimitException(
                'Rate limit bucket cannot be empty.',
            );
        }


        if ($cost < 1) {
            throw new InvalidRateLimitException(
                'Resolved rate limit cost must be greater than zero.',
            );
        }
    }
}
