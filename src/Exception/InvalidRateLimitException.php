<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Exception;

use InvalidArgumentException;

final class InvalidRateLimitException extends InvalidArgumentException implements RateLimiterExceptionInterface
{
}
