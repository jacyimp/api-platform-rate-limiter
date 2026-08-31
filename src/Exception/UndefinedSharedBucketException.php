<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Exception;

use InvalidArgumentException;

final class UndefinedSharedBucketException extends InvalidArgumentException implements RateLimiterExceptionInterface
{
}
