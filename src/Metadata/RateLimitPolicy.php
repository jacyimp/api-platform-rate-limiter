<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Metadata;

enum RateLimitPolicy: string
{
    case FIXED_WINDOW = 'fixed_window';
    case SLIDING_WINDOW = 'sliding_window';
}
