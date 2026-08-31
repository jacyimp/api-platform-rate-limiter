<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Core;

final readonly class RateLimitEnforcementResult
{
    /**
     * @param list<RateLimitResult> $results
     */
    public function __construct(
        public array $results,
    ) {
    }

    public function isAccepted(): bool
    {
        foreach ($this->results as $result) {
            if (!$result->accepted) {
                return false;
            }
        }

        return true;
    }

    public function rejectedResult(): ?RateLimitResult
    {
        foreach ($this->results as $result) {
            if (!$result->accepted) {
                return $result;
            }
        }

        return null;
    }
}
