<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Fixture;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

final class DocumentationMetadataFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function create(string $resourceClass): ResourceMetadataCollection
    {
        return new ResourceMetadataCollection($resourceClass, [new ApiResource(operations: [
            'documented' => new Get(extraProperties: [new RateLimit(100, '1 minute')]),
        ])]);
    }
}
