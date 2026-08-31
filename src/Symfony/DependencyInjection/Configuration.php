<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Symfony\DependencyInjection;

use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(
            'api_platform_rate_limiter',
        );

        $treeBuilder
            ->getRootNode()
            ->children()
            ->arrayNode('shared_buckets')
            ->defaultValue([])
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->children()
            ->integerNode('limit')
            ->isRequired()
            ->min(1)
            ->end()
            ->scalarNode('interval')
            ->isRequired()
            ->cannotBeEmpty()
            ->validate()
            ->ifTrue(
                static fn (mixed $value): bool => !is_string(
                    $value,
                ),
            )
            ->thenInvalid(
                'Shared rate limit interval must be a string.',
            )
            ->end()
            ->end()
            ->enumNode('policy')
            ->values([
                RateLimitPolicy::FIXED_WINDOW->value,
                RateLimitPolicy::SLIDING_WINDOW->value,
            ])
            ->defaultValue(
                RateLimitPolicy::SLIDING_WINDOW->value,
            )
            ->end()
            ->end()
            ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
