<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Metadata;

enum RateLimitPolicy: string
{
    case FIXED_WINDOW = 'fixed_window';
    case SLIDING_WINDOW = 'sliding_window';
}
