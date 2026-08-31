<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture;

use JacyImp\ApiPlatformRateLimiter\Contract\BucketResolverInterface;

final class FixedBucket implements BucketResolverInterface
{
    public function resolve(): string
    {
        return 'configured';
    }
}
