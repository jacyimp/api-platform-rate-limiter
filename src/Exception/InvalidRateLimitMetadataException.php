<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Exception;

use InvalidArgumentException;

final class InvalidRateLimitMetadataException extends InvalidArgumentException implements RateLimiterExceptionInterface
{
}
