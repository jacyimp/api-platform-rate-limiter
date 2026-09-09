<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\ApiPlatform;

use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use ApiPlatform\OpenApi\Factory\OpenApiFactory;
use ApiPlatform\OpenApi\Model\Operation;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitDescription;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitMetadataExtractor;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitResourceMetadataCollectionFactory;
use JacyImp\ApiPlatformRateLimiter\Core\IntervalNormalizer;
use JacyImp\ApiPlatformRateLimiter\Core\SharedRateLimitRegistry;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;

#[CoversClass(RateLimitResourceMetadataCollectionFactory::class)]
final class RateLimitResourceMetadataCollectionFactoryTest extends TestCase
{
    private ResourceMetadataCollection $innerCollection;

    #[Test]
    public function itAppendsToCustomOpenApiDescriptionsWithoutMutatingCachedMetadata(): void
    {
        $operation = new Get(
            description: 'Operation fallback.',
            openapi: new Operation(summary: 'Summary', description: 'Existing.'),
            extraProperties: [new RateLimit(100, '1 minute')],
        );
        $factory = $this->factory($operation);
        $firstOperation = $factory->create(self::class)->getOperation('get');
        $secondOperation = $factory->create(self::class)->getOperation('get');
        self::assertInstanceOf(Get::class, $firstOperation);
        self::assertInstanceOf(Get::class, $secondOperation);
        $first = $firstOperation->getOpenapi();
        $second = $secondOperation->getOpenapi();

        self::assertInstanceOf(Operation::class, $first);
        self::assertInstanceOf(Operation::class, $second);
        self::assertStringStartsWith("Existing.\n\n### Rate limits", $first->getDescription() ?? '');
        self::assertSame('Summary', $first->getSummary());
        self::assertSame($first->getDescription(), $second->getDescription());
        self::assertInstanceOf(Operation::class, $operation->getOpenapi());
        self::assertSame('Existing.', $operation->getOpenapi()->getDescription());
        $innerOperation = $this->innerCollection->getOperation('get');
        self::assertInstanceOf(Get::class, $innerOperation);
        self::assertSame($operation, $innerOperation);
    }

    #[Test]
    public function itLeavesUndocumentedAndUnlimitedOperationsUnchanged(): void
    {
        foreach ([new Get(), new Get(openapi: false, extraProperties: [new RateLimit(1, '1 minute')])] as $operation) {
            self::assertSame($operation, $this->factory($operation)->create(self::class)->getOperation('get'));
        }
    }

    #[Test]
    public function itIncludesRateLimitsInGeneratedOpenApi(): void
    {
        $names = self::createStub(ResourceNameCollectionFactoryInterface::class);
        $names->method('create')->willReturn(new ResourceNameCollection([self::class]));
        $arguments = [
            'resourceNameCollectionFactory' => $names,
            'resourceMetadataFactory' => $this->factory(new Get(
                uriTemplate: '/example',
                shortName: 'Example',
                description: 'Fetch example.',
                output: false,
                extraProperties: [new RateLimit(100, '1 minute')],
            )),
            'propertyNameCollectionFactory' => self::createStub(PropertyNameCollectionFactoryInterface::class),
            'propertyMetadataFactory' => self::createStub(PropertyMetadataFactoryInterface::class),
            'jsonSchemaFactory' => self::createStub(SchemaFactoryInterface::class),
            'filterLocator' => self::createStub(ContainerInterface::class),
        ];
        $reflection = new ReflectionClass(OpenApiFactory::class);
        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            if ($parameter->getName() !== 'jsonSchemaTypeFactory') {
                continue;
            }
            $arguments['jsonSchemaTypeFactory'] = null;
        }
        $factory = $reflection->newInstanceArgs($arguments);
        $description = $factory()->getPaths()->getPath('/example')?->getGet()?->getDescription();

        self::assertNotNull($description);
        self::assertStringStartsWith("Fetch example.\n\n### Rate limits", $description);
        self::assertStringContainsString('100 tokens per 1 minute', $description);
    }

    #[Test]
    public function itContinuesDecoratingAfterSkippedOperations(): void
    {
        $inner = self::createStub(ResourceMetadataCollectionFactoryInterface::class);
        $collection = new ResourceMetadataCollection(self::class, [
            new ApiResource(shortName: 'Example', operations: [
                'disabled' => new Get(openapi: false, extraProperties: [new RateLimit(1, '1 minute')]),
                'unlimited' => new Get(),
                'unsupported' => new Get(
                    openapi: new \ApiPlatform\OpenApi\Attributes\Webhook('event'),
                    extraProperties: [new RateLimit(2, '1 minute')],
                ),
                'limited' => new Get(extraProperties: [new RateLimit(3, '1 minute')]),
            ]),
        ]);
        $inner->method('create')->willReturn($collection);
        $factory = new RateLimitResourceMetadataCollectionFactory($inner, new RateLimitDescription(
            new RateLimitMetadataExtractor(),
            new SharedRateLimitRegistry([]),
            new IntervalNormalizer(),
        ));

        $result = $factory->create(self::class);
        $unsupported = $result->getOperation('unsupported');
        self::assertInstanceOf(Get::class, $unsupported);
        self::assertInstanceOf(\ApiPlatform\OpenApi\Attributes\Webhook::class, $unsupported->getOpenapi());
        $limited = $result->getOperation('limited');
        self::assertInstanceOf(Get::class, $limited);
        self::assertInstanceOf(Operation::class, $limited->getOpenapi());
        self::assertStringContainsString('3 tokens per 1 minute', $limited->getOpenapi()->getDescription() ?? '');
        $innerLimited = $collection->getOperation('limited');
        self::assertInstanceOf(Get::class, $innerLimited);
        self::assertNull($innerLimited->getOpenapi());
    }

    private function factory(Get $operation): RateLimitResourceMetadataCollectionFactory
    {
        $inner = self::createStub(ResourceMetadataCollectionFactoryInterface::class);
        $collection = $this->innerCollection = new ResourceMetadataCollection(self::class, [
            new ApiResource(shortName: 'Example', operations: ['get' => $operation]),
        ]);
        $inner->method('create')->willReturnCallback(
            static fn (string $class): ResourceMetadataCollection => $class === self::class
                ? $collection
                : new ResourceMetadataCollection($class),
        );

        return new RateLimitResourceMetadataCollectionFactory($inner, new RateLimitDescription(
            new RateLimitMetadataExtractor(),
            new SharedRateLimitRegistry([]),
            new IntervalNormalizer(),
        ));
    }
}
