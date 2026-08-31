<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Symfony\DependencyInjection;

use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use LogicException;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @internal
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(
            'api_platform_rate_limiter',
        );

        $rootNode = $this->requireArrayNode(
            $treeBuilder->getRootNode(),
        );

        $rootChildren = $rootNode->children();

        $globalsNode = $rootChildren
            ->arrayNode('globals');

        $globalsNode
            ->defaultValue([])
            ->useAttributeAsKey('name');

        $globalNode = $globalsNode->arrayPrototype();
        $globalNode
            ->validate()
            ->ifTrue(static function (array $value): bool {
                $hasLimit = $value['limit'] !== null;
                $hasLimitResolver = $value['limit_resolver'] !== null;
                $hasBucket = $value['bucket'] !== null;
                $hasBucketResolver = $value['bucket_resolver'] !== null;

                return $hasLimit === $hasLimitResolver
                    || (($hasLimit || $hasLimitResolver) !== ($value['interval'] !== null))
                    || ($hasLimit === false && $hasLimitResolver === false
                        && $hasBucket === false && $hasBucketResolver === false)
                    || ($hasBucket && $hasBucketResolver)
                    || ($value['cost_resolver'] !== null && $value['cost'] !== 1)
                    || ($value['identity'] !== null
                        && $value['identity_resolver'] !== null);
            })
            ->thenInvalid(
                'A global must configure exactly one of limit/limit_resolver with interval, '
                . 'or a bucket/bucket_resolver for shared lookup; dynamic and static '
                . 'variants of the same option cannot be combined.',
            );
        $globalChildren = $globalNode->children();

        $globalChildren
            ->integerNode('limit')
            ->defaultNull()
            ->min(1);

        foreach (['limit_resolver', 'bucket', 'bucket_resolver', 'cost_resolver'] as $option) {
            $globalChildren
                ->scalarNode($option)
                ->cannotBeEmpty()
                ->defaultNull();
        }

        $globalChildren
            ->integerNode('cost')
            ->min(1)
            ->defaultValue(1);

        $globalIntervalNode = $globalChildren
            ->scalarNode('interval');

        $globalIntervalNode
            ->defaultNull()
            ->cannotBeEmpty();

        $globalIntervalNode
            ->validate()
            ->ifTrue(
                static fn (mixed $value): bool => !is_string(
                    $value,
                ),
            )
            ->thenInvalid(
                'Global rate limit interval must be a string.',
            );

        $globalChildren
            ->enumNode('policy')
            ->values([
                RateLimitPolicy::FIXED_WINDOW->value,
                RateLimitPolicy::SLIDING_WINDOW->value,
            ])
            ->defaultValue(
                RateLimitPolicy::SLIDING_WINDOW->value,
            );

        foreach (['identity_resolver'] as $serviceOption) {
            $serviceNode = $globalChildren
                ->scalarNode($serviceOption)
                ->cannotBeEmpty()
                ->defaultNull();

            $serviceNode
                ->validate()
                ->ifTrue(
                    static fn (mixed $value): bool => $value !== null
                        && !is_string($value),
                )
                ->thenInvalid(
                    sprintf('%s must be a service ID string.', $serviceOption),
                );
        }

        $globalChildren->variableNode('identity')->defaultNull();
        $globalChildren->variableNode('when')->defaultNull();

        $sharedBucketsNode = $rootChildren
            ->arrayNode('buckets');

        $sharedBucketsNode
            ->defaultValue([])
            ->useAttributeAsKey('name');

        $bucketNode = $sharedBucketsNode->arrayPrototype();
        $bucketNode
            ->validate()
            ->ifTrue(static function (array $value): bool {
                $hasLimit = $value['limit'] !== null;
                $hasLimitResolver = $value['limit_resolver'] !== null;

                return $hasLimit === $hasLimitResolver
                    || ($value['cost_resolver'] !== null && $value['cost'] !== 1)
                    || ($value['identity'] !== null
                        && $value['identity_resolver'] !== null);
            })
            ->thenInvalid(
                'A bucket must configure exactly one of limit/limit_resolver; dynamic and '
                . 'static variants of the same option cannot be combined.',
            );
        $bucketChildren = $bucketNode->children();

        $bucketChildren
            ->integerNode('limit')
            ->defaultNull()
            ->min(1);

        foreach (['limit_resolver', 'cost_resolver'] as $option) {
            $bucketChildren
                ->scalarNode($option)
                ->cannotBeEmpty()
                ->defaultNull();
        }

        $bucketChildren
            ->integerNode('cost')
            ->min(1)
            ->defaultValue(1);

        $intervalNode = $bucketChildren
            ->scalarNode('interval');

        $intervalNode
            ->isRequired()
            ->cannotBeEmpty();

        $intervalNode
            ->validate()
            ->ifTrue(
                static fn (mixed $value): bool => !is_string(
                    $value,
                ),
            )
            ->thenInvalid(
                'Shared rate limit interval must be a string.',
            );

        $bucketChildren
            ->enumNode('policy')
            ->values([
                RateLimitPolicy::FIXED_WINDOW->value,
                RateLimitPolicy::SLIDING_WINDOW->value,
            ])
            ->defaultValue(
                RateLimitPolicy::SLIDING_WINDOW->value,
            );

        foreach (['identity_resolver'] as $serviceOption) {
            $serviceNode = $bucketChildren
                ->scalarNode($serviceOption)
                ->cannotBeEmpty()
                ->defaultNull();

            $serviceNode
                ->validate()
                ->ifTrue(
                    static fn (mixed $value): bool => $value !== null
                        && !is_string($value),
                )
                ->thenInvalid(
                    sprintf('%s must be a service ID string.', $serviceOption),
                );
        }

        $bucketChildren->variableNode('identity')->defaultNull();
        $bucketChildren->variableNode('when')->defaultNull();

        $rootChildren
            ->scalarNode('storage')
            ->cannotBeEmpty()
            ->defaultNull();

        $rootChildren
            ->scalarNode('cache_pool')
            ->cannotBeEmpty()
            ->defaultValue('cache.app');

        return $treeBuilder;
    }

    private function requireArrayNode(
        NodeDefinition $node,
    ): ArrayNodeDefinition {
        if (!$node instanceof ArrayNodeDefinition) {
            throw new LogicException(
                'Rate limiter configuration root must be an array node.',
            );
        }

        return $node;
    }
}
