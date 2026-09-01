<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Core;

use DateInterval;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AllOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AnyOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Condition;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Not;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\RateLimitCondition;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicBucket;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicCost;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\CompositeIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\FirstAvailableIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\Identity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\IdentityExpression;
use JacyImp\ApiPlatformRateLimiter\Metadata\Interval;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;

/** @internal */
final class RateLimitConfigurationFactory
{
    /**
     * @param array<string, array<string, mixed>> $configuration
     *
     * @return array<string, RateLimit>
     */
    public function buckets(array $configuration): array
    {
        $rateLimits = [];

        foreach ($configuration as $name => $values) {
            $rateLimits[$name] = $this->rateLimit($values, false);
        }

        return $rateLimits;
    }

    /**
     * @param array<string, array<string, mixed>> $configuration
     *
     * @return array<string, RateLimit>
     */
    public function globals(array $configuration): array
    {
        $rateLimits = [];

        foreach ($configuration as $name => $values) {
            $rateLimits[$name] = $this->rateLimit($values, true);
        }

        return $rateLimits;
    }

    /** @param array<string, mixed> $values */
    private function rateLimit(array $values, bool $allowBucket): RateLimit
    {
        $limit = $this->limit($values['limit'] ?? null);
        $bucket = $this->bucket($allowBucket ? ($values['bucket'] ?? null) : null);
        $cost = $this->cost($values['cost'] ?? 1);
        $policy = $this->policy($values['policy'] ?? RateLimitPolicy::SLIDING_WINDOW);
        $interval = $values['interval'] ?? null;

        if (
            !is_string($interval) && !$interval instanceof DateInterval
            && !$interval instanceof Interval && $interval !== null
        ) {
            throw new \InvalidArgumentException('Rate limit interval must be a string or interval object.');
        }

        return new RateLimit(
            limit: $limit,
            interval: $interval,
            bucket: $bucket,
            cost: $cost,
            identity: isset($values['identity'])
                ? $this->identity($values['identity'])
                : null,
            when: isset($values['when']) ? $this->condition($values['when']) : null,
            policy: $policy,
        );
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return non-empty-string
     */
    private function resolver(array $value): string
    {
        $resolver = $value['resolver'] ?? null;

        if (!is_string($resolver) || $resolver === '' || trim($resolver) === '') {
            throw new \InvalidArgumentException('A dynamic value requires a non-empty resolver.');
        }

        return $resolver;
    }

    private function limit(mixed $value): int|DynamicLimit|null
    {
        if (is_array($value)) {
            return new DynamicLimit($this->resolver($value));
        }

        if (!is_int($value) && $value !== null) {
            throw new \InvalidArgumentException('Rate limit must be an integer or resolver mapping.');
        }

        return $value;
    }

    private function bucket(mixed $value): string|DynamicBucket|null
    {
        if (is_array($value)) {
            return new DynamicBucket($this->resolver($value));
        }

        if (!is_string($value) && $value !== null) {
            throw new \InvalidArgumentException('Rate limit bucket must be a string or resolver mapping.');
        }

        return $value;
    }

    private function cost(mixed $value): int|DynamicCost
    {
        if (is_array($value)) {
            return new DynamicCost($this->resolver($value));
        }

        if (!is_int($value)) {
            throw new \InvalidArgumentException('Rate limit cost must be an integer or resolver mapping.');
        }

        return $value;
    }

    private function policy(mixed $value): RateLimitPolicy
    {
        if ($value instanceof RateLimitPolicy) {
            return $value;
        }

        if (!is_string($value)) {
            throw new \InvalidArgumentException('Rate limit policy must be a string.');
        }

        return RateLimitPolicy::from($value);
    }

    private function identity(mixed $value): IdentityExpression
    {
        if (is_string($value)) {
            return new Identity($value);
        }

        if (!is_array($value) || count($value) !== 1) {
            throw new \InvalidArgumentException('Invalid identity expression.');
        }

        $operator = array_key_first($value);
        $children = is_string($operator) ? $value[$operator] : null;
        if (!is_array($children)) {
            throw new \InvalidArgumentException('Identity expression children must be a list.');
        }

        $expressions = array_map(
            fn (mixed $child): IdentityExpression => $this->identity($child),
            array_values($children),
        );

        return match ($operator) {
            'composite' => new CompositeIdentity($expressions),
            'first_available' => new FirstAvailableIdentity($expressions),
            default => throw new \InvalidArgumentException(sprintf(
                'Unknown identity operator "%s".',
                $operator,
            )),
        };
    }

    private function condition(mixed $value): RateLimitCondition
    {
        if (is_string($value)) {
            return new Condition($value);
        }

        if (!is_array($value) || count($value) !== 1) {
            throw new \InvalidArgumentException('Invalid condition expression.');
        }

        $operator = array_key_first($value);
        $operand = is_string($operator) ? $value[$operator] : null;
        if ($operator === 'not') {
            return new Not($this->condition($operand));
        }

        if (!is_array($operand)) {
            throw new \InvalidArgumentException('Condition expression children must be a list.');
        }

        $conditions = [];
        foreach ($operand as $child) {
            $conditions[] = $this->condition($child);
        }

        return match ($operator) {
            'all_of' => new AllOf($conditions),
            'any_of' => new AnyOf($conditions),
            default => throw new \InvalidArgumentException(sprintf(
                'Unknown condition operator "%s".',
                $operator,
            )),
        };
    }
}
