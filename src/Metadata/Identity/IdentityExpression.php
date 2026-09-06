<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata\Identity;

/**
 * Marks a declarative identity expression accepted by rate-limit metadata.
 *
 * Resolver class names select one resolver; expressions compose several.
 */
interface IdentityExpression
{
}
