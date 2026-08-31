<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Core;

use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AllOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AnyOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Condition;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Not;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\RateLimitCondition;

/** @internal */
final readonly class RateLimitConditionEvaluator
{
    public function __construct(private RateLimitStrategyRegistry $strategyRegistry)
    {
    }

    public function matches(RateLimitCondition $condition): bool
    {
        if ($condition instanceof Condition) {
            return $this->strategyRegistry->condition($condition->service)->matches();
        }

        if ($condition instanceof Not) {
            return !$this->matches($condition->condition);
        }

        if ($condition instanceof AllOf) {
            foreach ($condition->conditions as $child) {
                if (!$this->matches($child)) {
                    return false;
                }
            }

            return true;
        }

        if ($condition instanceof AnyOf) {
            foreach ($condition->conditions as $child) {
                if ($this->matches($child)) {
                    return true;
                }
            }

            return false;
        }

        throw new \LogicException(sprintf(
            'Unsupported rate limit condition expression "%s".',
            $condition::class,
        ));
    }
}
