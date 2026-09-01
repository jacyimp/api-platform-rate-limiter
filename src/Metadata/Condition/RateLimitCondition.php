<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata\Condition;

/**
 * Marks a declarative condition accepted by rate-limit metadata.
 *
 * For example, `Condition` references one service and `AllOf` composes several.
 */
interface RateLimitCondition
{
}
