<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Core;

use DateInterval;
use Generator;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitConfigurationFactory;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AllOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AnyOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Not;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicBucket;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicCost;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\CompositeIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\FirstAvailableIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Interval;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitConfigurationFactory::class)]
final class RateLimitConfigurationFactoryTest extends TestCase
{
    #[Test]
    public function itCreatesConfiguredBucketsWithDynamicValuesAndExpressions(): void
    {
        $factory = new RateLimitConfigurationFactory();
        $buckets = $factory->buckets([
            'catalog' => [
                'limit' => ['resolver' => 'app.limit'],
                'interval' => new Interval(minutes: 1),
                'cost' => ['resolver' => 'app.cost'],
                'identity' => [
                    'composite' => [
                        'tenant',
                        ['first_available' => ['user', 'ip']],
                    ],
                ],
                'when' => [
                    'all_of' => [
                        'authenticated',
                        ['any_of' => [['not' => 'blocked'], 'premium']],
                    ],
                ],
                'policy' => RateLimitPolicy::FIXED_WINDOW,
            ],
        ]);

        $rateLimit = $buckets['catalog'];

        self::assertInstanceOf(DynamicLimit::class, $rateLimit->limit);
        self::assertSame('app.limit', $rateLimit->limit->resolver);
        self::assertInstanceOf(DynamicCost::class, $rateLimit->cost);
        self::assertSame('app.cost', $rateLimit->cost->resolver);
        self::assertNull($rateLimit->bucket);
        self::assertInstanceOf(CompositeIdentity::class, $rateLimit->identity);
        self::assertInstanceOf(FirstAvailableIdentity::class, $rateLimit->identity->identities[1]);
        self::assertInstanceOf(AllOf::class, $rateLimit->when);
        self::assertInstanceOf(AnyOf::class, $rateLimit->when->conditions[1]);
        self::assertInstanceOf(Not::class, $rateLimit->when->conditions[1]->conditions[0]);
        self::assertSame(RateLimitPolicy::FIXED_WINDOW, $rateLimit->policy);
    }

    #[Test]
    public function itCreatesGlobalsThatReferenceStaticAndDynamicBuckets(): void
    {
        $factory = new RateLimitConfigurationFactory();
        $globals = $factory->globals([
            'static' => ['bucket' => 'catalog'],
            'dynamic' => ['bucket' => ['resolver' => 'app.bucket']],
            'inline' => [
                'limit' => 10,
                'interval' => new DateInterval('PT1M'),
                'policy' => 'fixed_window',
            ],
        ]);

        self::assertSame('catalog', $globals['static']->bucket);
        self::assertInstanceOf(DynamicBucket::class, $globals['dynamic']->bucket);
        self::assertSame('app.bucket', $globals['dynamic']->bucket->resolver);
        self::assertSame(10, $globals['inline']->limit);
        self::assertSame(1, $globals['inline']->cost);
        self::assertSame(RateLimitPolicy::FIXED_WINDOW, $globals['inline']->policy);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    #[Test]
    #[DataProvider('invalidConfigurationProvider')]
    public function itRejectsInvalidConfiguration(
        array $configuration,
        bool $global,
        string $message,
    ): void {
        $factory = new RateLimitConfigurationFactory();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        if ($global) {
            $factory->globals(['invalid' => $configuration]);

            return;
        }

        $factory->buckets(['invalid' => $configuration]);
    }

    /** @return Generator<string, array{array<string, mixed>, bool, string}> */
    public static function invalidConfigurationProvider(): Generator
    {
        yield 'interval type' => [
            ['limit' => 10, 'interval' => 60],
            false,
            'Rate limit interval must be a string or interval object.',
        ];
        yield 'missing dynamic resolver' => [
            ['limit' => [], 'interval' => '1 minute'],
            false,
            'A dynamic value requires a non-empty resolver.',
        ];
        yield 'blank dynamic resolver' => [
            ['limit' => ['resolver' => ' '], 'interval' => '1 minute'],
            false,
            'A dynamic value requires a non-empty resolver.',
        ];
        yield 'limit type' => [
            ['limit' => '10', 'interval' => '1 minute'],
            false,
            'Rate limit must be an integer or resolver mapping.',
        ];
        yield 'bucket type' => [
            ['bucket' => 123],
            true,
            'Rate limit bucket must be a string or resolver mapping.',
        ];
        yield 'cost type' => [
            ['limit' => 10, 'interval' => '1 minute', 'cost' => '2'],
            false,
            'Rate limit cost must be an integer or resolver mapping.',
        ];
        yield 'policy type' => [
            ['limit' => 10, 'interval' => '1 minute', 'policy' => 1],
            false,
            'Rate limit policy must be a string.',
        ];
        yield 'identity shape' => [
            ['limit' => 10, 'interval' => '1 minute', 'identity' => []],
            false,
            'Invalid identity expression.',
        ];
        yield 'identity children' => [
            ['limit' => 10, 'interval' => '1 minute', 'identity' => ['composite' => 'user']],
            false,
            'Identity expression children must be a list.',
        ];
        yield 'identity operator' => [
            ['limit' => 10, 'interval' => '1 minute', 'identity' => ['unknown' => ['user']]],
            false,
            'Unknown identity operator "unknown".',
        ];
        yield 'condition shape' => [
            ['limit' => 10, 'interval' => '1 minute', 'when' => []],
            false,
            'Invalid condition expression.',
        ];
        yield 'condition children' => [
            ['limit' => 10, 'interval' => '1 minute', 'when' => ['all_of' => 'enabled']],
            false,
            'Condition expression children must be a list.',
        ];
        yield 'condition operator' => [
            ['limit' => 10, 'interval' => '1 minute', 'when' => ['unknown' => ['enabled']]],
            false,
            'Unknown condition operator "unknown".',
        ];
    }
}
