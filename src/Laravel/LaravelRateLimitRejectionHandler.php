<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Laravel;

use DateTimeZone;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejection;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejectionHandlerInterface;

/** @internal */
final class LaravelRateLimitRejectionHandler implements RateLimitRejectionHandlerInterface
{
    public function reject(RateLimitRejection $rejection): never
    {
        $retryAfter = $rejection
            ->retryAfter
            ->setTimezone(new DateTimeZone('GMT'))
            ->format('D, d M Y H:i:s \G\M\T');

        throw new HttpResponseException(new JsonResponse(
            data: ['message' => 'Rate limit exceeded.'],
            status: 429,
            headers: [
                'RateLimit-Limit' => $rejection->limit,
                'RateLimit-Remaining' => $rejection->remaining,
                'Retry-After' => $retryAfter,
            ],
        ));
    }
}
