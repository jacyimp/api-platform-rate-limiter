<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitMetadataException;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

/**
 * @internal
 */
final class RateLimitMetadataExtractor
{
    /**
     * @return list<RateLimit>
     */
    public function extract(Operation $operation): array
    {
        $extraProperties = $operation->getExtraProperties();

        $rateLimits = [];

        if (isset($extraProperties[RateLimit::class])) {
            $metadata = $extraProperties[RateLimit::class];
            $rateLimits = $metadata instanceof RateLimit
                ? [$metadata]
                : $metadata;
            if (!is_array($rateLimits)) {
                throw new InvalidRateLimitMetadataException(sprintf(
                    'Extra property "%s" must be a %s or a list of them.',
                    RateLimit::class,
                    RateLimit::class,
                ));
            }

            foreach ($rateLimits as $rateLimit) {
                if (!$rateLimit instanceof RateLimit) {
                    throw new InvalidRateLimitMetadataException(sprintf(
                        'Extra property "%s" must contain only instances of %s.',
                        RateLimit::class,
                        RateLimit::class,
                    ));
                }
            }
        }

        return array_values($rateLimits);
    }
}
