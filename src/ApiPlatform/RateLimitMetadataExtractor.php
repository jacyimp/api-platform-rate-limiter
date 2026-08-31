<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use InvalidArgumentException;
use Jacyimp\ApiPlatformRateLimiter\Metadata\OperationRateLimit;
use Jacyimp\ApiPlatformRateLimiter\Metadata\SharedRateLimit;

final class RateLimitMetadataExtractor
{
    /**
     * @return list<OperationRateLimit|SharedRateLimit>
     */
    public function extract(Operation $operation): array
    {
        $extraProperties = $operation->getExtraProperties();

        $rateLimits = [];

        if (isset($extraProperties[OperationRateLimit::class])) {
            $operationRateLimit = $extraProperties[OperationRateLimit::class];

            if (!$operationRateLimit instanceof OperationRateLimit) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Extra property "%s" must be an instance of %s.',
                        OperationRateLimit::class,
                        OperationRateLimit::class,
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
