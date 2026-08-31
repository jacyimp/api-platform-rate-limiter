<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitMetadataException;
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
        }

        $validated = [];
        foreach ($rateLimits as $rateLimit) {
            $validated[] = $this->requireRateLimit($rateLimit);
        }

        return $validated;
    }

    /**
     * @return list<BypassRateLimit>
     */
    public function extractBypasses(Operation $operation): array
    {
        $extraProperties = $operation->getExtraProperties();

        if (!isset($extraProperties[BypassRateLimit::class])) {
            return [];
        }

        $metadata = $extraProperties[BypassRateLimit::class];
        $bypasses = $metadata instanceof BypassRateLimit
            ? [$metadata]
            : $metadata;
        if (!is_array($bypasses)) {
            throw new InvalidRateLimitMetadataException(sprintf(
                'Extra property "%s" must be a %s or a list of them.',
                BypassRateLimit::class,
                BypassRateLimit::class,
            ));
        }

        $validated = [];
        foreach ($bypasses as $bypass) {
            $validated[] = $this->requireBypass($bypass);
        }

        return $validated;
    }

    private function requireRateLimit(mixed $rateLimit): RateLimit
    {
        if (!$rateLimit instanceof RateLimit) {
            throw new InvalidRateLimitMetadataException(sprintf(
                'Extra property "%s" must contain only instances of %s.',
                RateLimit::class,
                RateLimit::class,
            ));
        }

        return $rateLimit;
    }

    private function requireBypass(mixed $bypass): BypassRateLimit
    {
        if (!$bypass instanceof BypassRateLimit) {
            throw new InvalidRateLimitMetadataException(sprintf(
                'Extra property "%s" must contain only instances of %s.',
                BypassRateLimit::class,
                BypassRateLimit::class,
            ));
        }

        return $bypass;
    }
}
