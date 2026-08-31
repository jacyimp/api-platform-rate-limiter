<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata;

use JacyImp\ApiPlatformRateLimiter\Exception\InvalidIntervalException;

final readonly class Interval
{
    public function __construct(
        public int $days = 0,
        public int $hours = 0,
        public int $minutes = 0,
        public int $seconds = 0,
    ) {
        if (
            $days < 0
            || $hours < 0
            || $minutes < 0
            || $seconds < 0
        ) {
            throw new InvalidIntervalException(
                'Interval values cannot be negative.',
            );
        }

        if (
            $days === 0
            && $hours === 0
            && $minutes === 0
            && $seconds === 0
        ) {
            throw new InvalidIntervalException(
                'Interval must be greater than zero.',
            );
        }
    }
}
