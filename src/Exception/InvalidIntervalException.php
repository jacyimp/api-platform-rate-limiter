<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Exception;

use InvalidArgumentException;

final class InvalidIntervalException extends InvalidArgumentException implements RateLimiterExceptionInterface
{
}
