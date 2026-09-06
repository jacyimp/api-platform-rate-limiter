<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use JacyImp\ApiPlatformRateLimiter\Metadata\BypassRateLimit;
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
        $rateLimits = [];
        foreach ($operation->getExtraProperties() ?? [] as $key => $metadata) {
            if (!is_int($key) || !($metadata instanceof RateLimit)) {
                continue;
            }

            $rateLimits[] = $metadata;
        }

        return $rateLimits;
    }

    /**
     * @return list<BypassRateLimit>
     */
    public function extractBypasses(Operation $operation): array
    {
        $bypasses = [];
        foreach ($operation->getExtraProperties() ?? [] as $key => $metadata) {
            if (!is_int($key) || !($metadata instanceof BypassRateLimit)) {
                continue;
            }

            $bypasses[] = $metadata;
        }

        return $bypasses;
    }
}
