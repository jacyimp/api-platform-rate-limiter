<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\ApiPlatform;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;

/** @internal */
final readonly class RateLimitResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(
        private ResourceMetadataCollectionFactoryInterface $decorated,
        private RateLimitDescription $description,
    ) {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = clone $this->decorated->create($resourceClass);
        if (!class_exists(OpenApiOperation::class)) {
            return $collection;
        }
        foreach ($collection as $index => $resource) {
            $operations = $resource->getOperations();
            if ($operations === null) {
                continue;
            }
            $operations = clone $operations;
            // API Platform 3.4 does not type its operations iterator.
            /**
             * @var string $name
             * @var \ApiPlatform\Metadata\HttpOperation $operation
             */
            foreach ($operations as $name => $operation) {
                if ($operation->getOpenapi() === false) {
                    continue;
                }
                $description = $this->description->describe($operation, $name);
                if ($description === '') {
                    continue;
                }
                $openapi = $operation->getOpenapi();
                if (is_object($openapi) && !$openapi instanceof OpenApiOperation) {
                    continue;
                }
                $existing = $openapi instanceof OpenApiOperation
                    ? ($openapi->getDescription() ?? $operation->getDescription())
                    : $operation->getDescription();
                $description = ($existing === null || $existing === '' ? '' : $existing . "\n\n") . $description;
                $openapi = $openapi instanceof OpenApiOperation ? $openapi : new OpenApiOperation();
                $operation = $operation->withOpenapi($openapi->withDescription($description));
                $operations->add($name, $operation);
            }
            $collection[$index] = $resource->withOperations($operations);
        }

        return $collection;
    }
}
