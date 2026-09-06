<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata;

use DateInterval;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\RateLimitCondition;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\IdentityExpression;

/**
 * Declares a quota for an API Platform operation or resource.
 *
 * Omit limit and interval only when referencing a configured bucket.
 * Example: `new RateLimit(limit: 100, interval: '1 minute')`.
 *
 * @phpstan-type IdentityResolverClass class-string<\JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface>
 * @phpstan-type ConditionClass class-string<\JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface>
 */
final readonly class RateLimit
{
    /**
     * @param int|class-string<\JacyImp\ApiPlatformRateLimiter\Contract\LimitResolverInterface>|null $limit
     * @param class-string<\JacyImp\ApiPlatformRateLimiter\Contract\BucketResolverInterface>|null $bucketResolver
     * @param int|class-string<\JacyImp\ApiPlatformRateLimiter\Contract\CostResolverInterface> $cost
     * @param IdentityResolverClass|IdentityExpression|null $identity
     * @param ConditionClass|RateLimitCondition|null $when
     */
    public function __construct(
        public int|string|null $limit = null,
        public string|DateInterval|null $interval = null,
        public ?string $bucket = null,
        public ?string $bucketResolver = null,
        public int|string $cost = 1,
        public string|IdentityExpression|null $identity = null,
        public string|RateLimitCondition|null $when = null,
        public RateLimitPolicy $policy = RateLimitPolicy::SLIDING_WINDOW,
    ) {
        if (is_int($limit) && $limit < 1) {
            throw new InvalidRateLimitException(
                'Rate limit must be greater than zero.',
            );
        }

        if (($limit === null) !== ($interval === null)) {
            throw new InvalidRateLimitException(
                'Rate limit and interval must either both be set or both be omitted.',
            );
        }

        if ($limit === null && $bucket === null && $bucketResolver === null) {
            throw new InvalidRateLimitException(
                'An operation-specific rate limit requires a limit and interval.',
            );
        }

        if ($bucket !== null && $bucketResolver !== null) {
            throw new InvalidRateLimitException(
                'Rate limit cannot define both a bucket and a bucket resolver.',
            );
        }

        if ($bucket !== null && trim($bucket) === '') {
            throw new InvalidRateLimitException(
                'Rate limit bucket cannot be empty.',
            );
        }

        if ($bucketResolver !== null && trim($bucketResolver) === '') {
            throw new InvalidRateLimitException(
                'Rate limit bucket resolver cannot be empty.',
            );
        }

        if (is_int($cost) && $cost < 1) {
            throw new InvalidRateLimitException(
                'Rate limit cost must be greater than zero.',
            );
        }
    }
}
