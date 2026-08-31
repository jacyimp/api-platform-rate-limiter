<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Symfony;

use DateTimeZone;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejection;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejectionHandlerInterface;
use JacyImp\ApiPlatformRateLimiter\Exception\RateLimitExceededException;

/**
 * @internal
 */
final class SymfonyRateLimitRejectionHandler implements RateLimitRejectionHandlerInterface
{
    public function reject(RateLimitRejection $rejection): never
    {
        throw new RateLimitExceededException(
            retryAfter: $rejection
                ->retryAfter
                ->setTimezone(new DateTimeZone('GMT'))
                ->format('D, d M Y H:i:s \G\M\T'),
            message: 'Rate limit exceeded.',
            headers: [
                'RateLimit-Limit' => (string) $rejection->limit,
                'RateLimit-Remaining' => (string) $rejection->remaining,
            ],
        );
    }
}
