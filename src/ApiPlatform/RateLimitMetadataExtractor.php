<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitMetadataException;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\SharedRateLimit;

/**
 * @internal
 */
final class RateLimitMetadataExtractor
{
    /**
     * @return list<RateLimit|SharedRateLimit>
     */
    public function extract(Operation $operation): array
    {
        $extraProperties = $operation->getExtraProperties();

        $rateLimits = [];

        if (isset($extraProperties[RateLimit::class])) {
            $rateLimit = $extraProperties[RateLimit::class];

            if (!$rateLimit instanceof RateLimit) {
                throw new InvalidRateLimitMetadataException(
                    sprintf(
                        'Extra property "%s" must be an instance of %s.',
                        RateLimit::class,
                        RateLimit::class,
                    ),
                );
            }

            $rateLimits[] = $rateLimit;
        }

        if (isset($extraProperties[SharedRateLimit::class])) {
            $sharedRateLimit = $extraProperties[SharedRateLimit::class];

            if (!$sharedRateLimit instanceof SharedRateLimit) {
                throw new InvalidRateLimitMetadataException(
                    sprintf(
                        'Extra property "%s" must be an instance of %s.',
                        SharedRateLimit::class,
                        SharedRateLimit::class,
                    ),
                );
            }

            $rateLimits[] = $sharedRateLimit;
        }

        return $rateLimits;
    }
}
