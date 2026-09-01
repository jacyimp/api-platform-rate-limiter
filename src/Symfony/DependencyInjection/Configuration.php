<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Symfony\DependencyInjection;

use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimitPolicy;
use LogicException;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/** @internal */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('api_platform_rate_limiter');
        $rootNode = $this->requireArrayNode($treeBuilder->getRootNode());
        $rootChildren = $rootNode->children();

        $globalsNode = $rootChildren->arrayNode('globals');
        $globalNode = $globalsNode->arrayPrototype();
        $this->configureRateLimit($globalNode, false);
        $globalNode
            ->validate()
            ->ifTrue(static fn (array $value): bool => ($value['limit'] === null)
                !== ($value['interval'] === null)
                || ($value['limit'] === null && $value['bucket'] === null))
            ->thenInvalid(
                'A global must configure limit with interval, or bucket for shared lookup.',
            );

        $bucketsNode = $rootChildren->arrayNode('buckets');
        $bucketNode = $bucketsNode->arrayPrototype();
        $this->configureRateLimit($bucketNode, true);

        $rootChildren->scalarNode('storage')->cannotBeEmpty()->defaultNull();
        $rootChildren
            ->scalarNode('cache_pool')
            ->cannotBeEmpty()
            ->defaultValue('cache.app');

        return $treeBuilder;
    }

    private function configureRateLimit(ArrayNodeDefinition $node, bool $requireDefinition): void
    {
        $children = $node->children();

        $limit = $children->variableNode('limit');
        $limit->defaultNull();
        $limit
            ->validate()
            ->ifTrue(static fn (mixed $value): bool => $value !== null
                && !self::isPositiveIntegerOrResolver($value))
            ->thenInvalid('limit must be a positive integer or a resolver mapping.');

        $interval = $children->scalarNode('interval');
        $requireDefinition ? $interval->isRequired() : $interval->defaultNull();
        $interval
            ->cannotBeEmpty()
            ->validate()
            ->ifTrue(static fn (mixed $value): bool => $value !== null && !is_string($value))
            ->thenInvalid('interval must be a string.');

        $children
            ->enumNode('policy')
            ->values([
                RateLimitPolicy::FIXED_WINDOW->value,
                RateLimitPolicy::SLIDING_WINDOW->value,
            ])
            ->defaultValue(RateLimitPolicy::SLIDING_WINDOW->value);

        $children->variableNode('identity')->defaultNull();
        $children->variableNode('when')->defaultNull();

        if (!$requireDefinition) {
            $bucket = $children->variableNode('bucket');
            $bucket->defaultNull();
            $bucket
                ->validate()
                ->ifTrue(static fn (mixed $value): bool => $value !== null
                    && !self::isNonEmptyStringOrResolver($value))
                ->thenInvalid('bucket must be a non-empty string or a resolver mapping.');
        }

        $cost = $children->variableNode('cost');
        $cost->defaultValue(1);
        $cost
            ->validate()
            ->ifTrue(static fn (mixed $value): bool => !self::isPositiveIntegerOrResolver($value))
            ->thenInvalid('cost must be a positive integer or a resolver mapping.');

        if (!$requireDefinition) {
            return;
        }

        $node
            ->validate()
            ->ifTrue(static fn (array $value): bool => $value['limit'] === null)
            ->thenInvalid('A bucket must configure limit.');
    }

    private static function isPositiveIntegerOrResolver(mixed $value): bool
    {
        return (is_int($value) && $value > 0) || self::isResolver($value);
    }

    private static function isNonEmptyStringOrResolver(mixed $value): bool
    {
        return (is_string($value) && trim($value) !== '') || self::isResolver($value);
    }

    private static function isResolver(mixed $value): bool
    {
        return is_array($value)
            && array_keys($value) === ['resolver']
            && is_string($value['resolver'])
            && trim($value['resolver']) !== '';
    }

    private function requireArrayNode(NodeDefinition $node): ArrayNodeDefinition
    {
        if (!$node instanceof ArrayNodeDefinition) {
            throw new LogicException('Rate limiter configuration root must be an array node.');
        }

        return $node;
    }
}
