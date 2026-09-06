<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata\Condition;

/**
 * Marks a declarative condition accepted by rate-limit metadata.
 *
 * Resolver class names select one condition; expressions compose several.
 */
interface RateLimitCondition
{
}
