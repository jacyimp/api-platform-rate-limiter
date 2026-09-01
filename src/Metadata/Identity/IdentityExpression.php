<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata\Identity;

/**
 * Marks a declarative identity expression accepted by rate-limit metadata.
 *
 * For example, `Identity` selects one resolver and `CompositeIdentity` combines several.
 */
interface IdentityExpression
{
}
