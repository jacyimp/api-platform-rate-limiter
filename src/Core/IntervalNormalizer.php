<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Core;

use DateInterval;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidIntervalException;
use JacyImp\ApiPlatformRateLimiter\Metadata\Interval;

/**
 * @internal
 */
final class IntervalNormalizer
{
    public function normalize(
        string|DateInterval|Interval $interval,
    ): int {
        return match (true) {
            is_string($interval) => $this->fromString($interval),
            $interval instanceof Interval => $this->fromInterval($interval),
            $interval instanceof DateInterval => $this->fromDateInterval($interval),
        };
    }

    private function fromString(string $interval): int
    {
        if (trim($interval) === '') {
            throw new InvalidIntervalException(
                'Rate limit interval cannot be empty.',
            );
        }

        $dateInterval = DateInterval::createFromDateString($interval);

        if ($dateInterval === false) {
            throw new InvalidIntervalException(
                sprintf('Invalid rate limit interval "%s".', $interval),
            );
        }

        return $this->fromDateInterval($dateInterval);
    }

    private function fromInterval(Interval $interval): int
    {
        return ($interval->days * 86_400)
            + ($interval->hours * 3_600)
            + ($interval->minutes * 60)
            + $interval->seconds;
    }

    private function fromDateInterval(DateInterval $interval): int
    {
        if ($interval->y !== 0 || $interval->m !== 0) {
            throw new InvalidIntervalException(
                'Rate limit intervals cannot contain months or years.',
            );
        }

        if (
            $interval->invert === 1
            || $interval->d < 0
            || $interval->h < 0
            || $interval->i < 0
            || $interval->s < 0
        ) {
            throw new InvalidIntervalException(
                'Rate limit interval cannot be negative.',
            );
        }

        if ($interval->f > 0) {
            throw new InvalidIntervalException(
                'Rate limit interval cannot contain fractional seconds.',
            );
        }

        $seconds = ($interval->d * 86_400)
            + ($interval->h * 3_600)
            + ($interval->i * 60)
            + $interval->s;

        if ($seconds < 1) {
            throw new InvalidIntervalException(
                'Rate limit interval must be greater than zero.',
            );
        }

        return $seconds;
    }
}
