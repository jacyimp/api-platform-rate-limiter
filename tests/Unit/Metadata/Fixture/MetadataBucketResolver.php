<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Metadata\Fixture;

use JacyImp\ApiPlatformRateLimiter\Contract\BucketResolverInterface;

final class MetadataBucketResolver implements BucketResolverInterface
{
    public function resolve(): string
    {
        return 'catalog';
    }
}
