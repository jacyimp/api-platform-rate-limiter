<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Exception;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class RateLimitExceededException extends TooManyRequestsHttpException implements RateLimiterExceptionInterface
{
}
