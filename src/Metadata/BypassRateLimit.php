<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\RateLimitCondition;

/**
 * Exempts an API Platform operation or resource from matching rate limits.
 *
 * Omit the bucket to bypass every limit, or name one bucket to bypass only it.
 * Example: `new BypassRateLimit(bucket: 'catalog')`.
 */
final readonly class BypassRateLimit
{
    public function __construct(
        public ?string $bucket = null,
        public ?RateLimitCondition $when = null,
    ) {
        if ($bucket !== null && trim($bucket) === '') {
            throw new InvalidRateLimitException(
                'Bypass rate limit bucket cannot be empty.',
            );
        }
    }
}
