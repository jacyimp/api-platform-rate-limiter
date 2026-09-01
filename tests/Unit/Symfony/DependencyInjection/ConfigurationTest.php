<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Symfony\DependencyInjection;

use JacyImp\ApiPlatformRateLimiter\Symfony\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;

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
}
