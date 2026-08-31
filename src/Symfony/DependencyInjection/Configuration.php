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

        $globalNode = $rootChildren
            ->arrayNode('global');
        $globalChildren = $globalNode->children();

        $globalChildren
            ->integerNode('limit')
            ->isRequired()
            ->min(1);

        $globalIntervalNode = $globalChildren
            ->scalarNode('interval');

        $globalIntervalNode
            ->isRequired()
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

        foreach (['identity_resolver', 'when'] as $serviceOption) {
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

        $sharedBucketsNode = $rootChildren
            ->arrayNode('shared_buckets');

        $sharedBucketsNode
            ->defaultValue([])
            ->useAttributeAsKey('name');

        $bucketNode = $sharedBucketsNode->arrayPrototype();
        $bucketChildren = $bucketNode->children();

        $bucketChildren
            ->integerNode('limit')
            ->isRequired()
            ->min(1);

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

        foreach (['identity_resolver', 'when'] as $serviceOption) {
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
