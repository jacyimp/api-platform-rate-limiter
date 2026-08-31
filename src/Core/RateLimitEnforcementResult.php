<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Core;

/**
 * @internal
 */
final readonly class RateLimitEnforcementResult
{
    /**
     * @param list<RateLimitConsumption> $consumptions
     */
    public function __construct(
        public array $consumptions,
    ) {
    }

    public function isAccepted(): bool
    {
        return $this->rejectedConsumption() === null;
    }

    public function rejectedConsumption(): ?RateLimitConsumption
    {
        foreach ($this->consumptions as $consumption) {
            if (!$consumption->result->accepted) {
                return $consumption;
            }
        }

        return null;
    }
}
