<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use InvalidArgumentException;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\SharedRateLimit;

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
            $operationRateLimit = $extraProperties[RateLimit::class];

            if (!$operationRateLimit instanceof RateLimit) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Extra property "%s" must be an instance of %s.',
                        RateLimit::class,
                        RateLimit::class,
                    ),
                );
            }

            $rateLimits[] = $operationRateLimit;
        }

        if (isset($extraProperties[SharedRateLimit::class])) {
            $sharedRateLimit = $extraProperties[SharedRateLimit::class];

            if (!$sharedRateLimit instanceof SharedRateLimit) {
                throw new InvalidArgumentException(
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
