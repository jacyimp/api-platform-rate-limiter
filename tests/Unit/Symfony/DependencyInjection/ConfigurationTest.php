<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Symfony\DependencyInjection;

use Generator;
use JacyImp\ApiPlatformRateLimiter\Symfony\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    #[Test]
    public function itRecognizesResolverMappingsForValidatedValues(): void
    {
        $resolver = ['resolver' => 'app.resolver'];

        self::assertTrue($this->invokeBooleanHelper(
            'isPositiveIntegerOrResolver',
            $resolver,
        ));
        self::assertTrue($this->invokeBooleanHelper(
            'isNonEmptyStringOrResolver',
            $resolver,
        ));
        self::assertTrue($this->invokeBooleanHelper('isResolver', $resolver));
        self::assertTrue($this->invokeBooleanHelper(
            'isNonEmptyStringOrResolver',
            'catalog',
        ));
    }

    #[Test]
    public function itRejectsMalformedResolverValues(): void
    {
        $values = [
            'blank string' => ' ',
            'scalar' => 123,
            'empty mapping' => [],
            'empty resolver' => ['resolver' => ''],
            'blank resolver' => ['resolver' => ' '],
            'non-string resolver' => ['resolver' => 123],
            'extra option' => ['resolver' => 'app.resolver', 'extra' => true],
        ];

        foreach ($values as $description => $value) {
            self::assertFalse(
                $this->invokeBooleanHelper('isResolver', $value),
                $description,
            );
            self::assertFalse(
                $this->invokeBooleanHelper('isNonEmptyStringOrResolver', $value),
                $description,
            );
        }
    }

    #[Test]
    public function itClassifiesPositiveIntegerValues(): void
    {
        self::assertTrue($this->invokeBooleanHelper('isPositiveIntegerOrResolver', 1));
        self::assertFalse($this->invokeBooleanHelper('isPositiveIntegerOrResolver', 0));
        self::assertFalse($this->invokeBooleanHelper('isPositiveIntegerOrResolver', -1));
        self::assertFalse($this->invokeBooleanHelper('isPositiveIntegerOrResolver', 1.5));
        self::assertFalse($this->invokeBooleanHelper('isPositiveIntegerOrResolver', '1'));
    }

    #[Test]
    public function itProcessesDefaultsAndEveryValidDefinitionForm(): void
    {
        $defaults = $this->process([]);
        self::assertIsArray($defaults['globals']);
        self::assertSame([], $defaults['globals']);
        self::assertIsArray($defaults['buckets']);
        self::assertSame([], $defaults['buckets']);
        self::assertNull($defaults['storage']);
        self::assertSame('cache.app', $defaults['cache_pool']);

        $processed = $this->process([
            'globals' => [
                'inline' => [
                    'limit' => 1,
                    'interval' => '1 second',
                    'bucket' => 'inline-bucket',
                    'cost' => 1,
                    'policy' => 'fixed_window',
                ],
                'shared' => ['bucket' => 'catalog'],
            ],
            'buckets' => [
                'catalog' => [
                    'limit' => 10,
                    'interval' => '1 minute',
                    'cost' => ['resolver' => 'app.cost'],
                    'policy' => 'fixed_window',
                ],
            ],
        ]);

        $globals = $processed['globals'];
        self::assertIsArray($globals);
        self::assertSame(['inline', 'shared'], array_keys($globals));
        $inline = $globals['inline'];
        self::assertIsArray($inline);
        self::assertSame(1, $inline['limit']);
        self::assertSame('fixed_window', $inline['policy']);
        $shared = $globals['shared'];
        self::assertIsArray($shared);
        self::assertNull($shared['limit']);
        self::assertNull($shared['interval']);
        self::assertSame('catalog', $shared['bucket']);
        $buckets = $processed['buckets'];
        self::assertIsArray($buckets);
        self::assertSame(['catalog'], array_keys($buckets));
        $catalog = $buckets['catalog'];
        self::assertIsArray($catalog);
        self::assertSame(['resolver' => 'app.cost'], $catalog['cost']);
    }

    /** @param array<string, mixed> $configuration */
    #[Test]
    #[DataProvider('invalidTreeConfigurationProvider')]
    public function itRejectsInvalidTreeConfiguration(array $configuration): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process($configuration);
    }

    /** @return Generator<string, array{array<string, mixed>}> */
    public static function invalidTreeConfigurationProvider(): Generator
    {
        yield 'empty global' => [['globals' => ['invalid' => []]]];
        yield 'global limit without interval' => [[
            'globals' => ['invalid' => ['limit' => 1]],
        ]];
        yield 'global interval without limit' => [[
            'globals' => ['invalid' => ['interval' => '1 minute']],
        ]];
        yield 'bucket without limit' => [[
            'buckets' => ['invalid' => ['interval' => '1 minute']],
        ]];
        yield 'bucket without interval' => [[
            'buckets' => ['invalid' => ['limit' => 1]],
        ]];
        yield 'zero limit' => [[
            'globals' => ['invalid' => ['limit' => 0, 'interval' => '1 minute']],
        ]];
        yield 'non-integer limit' => [[
            'globals' => ['invalid' => ['limit' => '1', 'interval' => '1 minute']],
        ]];
        yield 'non-string interval' => [[
            'globals' => ['invalid' => ['limit' => 1, 'interval' => 60]],
        ]];
        yield 'empty interval' => [[
            'globals' => ['invalid' => ['limit' => 1, 'interval' => '']],
        ]];
        yield 'unknown policy' => [[
            'globals' => [
                'invalid' => [
                    'limit' => 1,
                    'interval' => '1 minute',
                    'policy' => 'unknown',
                ],
            ],
        ]];
        yield 'blank bucket' => [['globals' => ['invalid' => ['bucket' => ' ']]]];
        yield 'non-string bucket' => [['globals' => ['invalid' => ['bucket' => 123]]]];
        yield 'malformed bucket resolver' => [[
            'globals' => ['invalid' => ['bucket' => ['resolver' => ' ']]],
        ]];
        yield 'zero cost' => [[
            'globals' => [
                'invalid' => ['limit' => 1, 'interval' => '1 minute', 'cost' => 0],
            ],
        ]];
        yield 'non-integer cost' => [[
            'globals' => [
                'invalid' => ['limit' => 1, 'interval' => '1 minute', 'cost' => '1'],
            ],
        ]];
    }

    #[Test]
    public function itRejectsANonArrayConfigurationRoot(): void
    {
        $method = new ReflectionMethod(Configuration::class, 'requireArrayNode');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('configuration root must be an array node');

        $method->invoke(new Configuration(), new ScalarNodeDefinition('root'));
    }

    private function invokeBooleanHelper(string $methodName, mixed $value): bool
    {
        $method = new ReflectionMethod(Configuration::class, $methodName);
        $result = $method->invoke(null, $value);

        self::assertIsBool($result);

        return $result;
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return array<array-key, mixed>
     */
    private function process(array $configuration): array
    {
        return (new Processor())->processConfiguration(
            new Configuration(),
            [$configuration],
        );
    }
}
