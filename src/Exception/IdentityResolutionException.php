<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Exception;

use RuntimeException;

final class IdentityResolutionException extends RuntimeException implements RateLimiterExceptionInterface
{
}
