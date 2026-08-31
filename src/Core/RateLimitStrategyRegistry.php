<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Core;

use InvalidArgumentException;
use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;

/**
 * @internal
 */
final readonly class RateLimitStrategyRegistry
{
    /** @var array<string, IdentityResolverInterface> */
    private array $identityResolvers;

    /** @var array<string, RateLimitConditionInterface> */
    private array $conditions;

    /**
     * @param iterable<IdentityResolverInterface> $identityResolvers
     * @param iterable<RateLimitConditionInterface> $conditions
     */
    public function __construct(
        iterable $identityResolvers,
        iterable $conditions,
    ) {
        $this->identityResolvers = $this->index($identityResolvers);
        $this->conditions = $this->index($conditions);
    }

    public function identityResolver(
        string $serviceId,
    ): IdentityResolverInterface {
        return $this->identityResolvers[$serviceId]
            ?? throw new InvalidArgumentException(sprintf(
                'Identity resolver service "%s" is not registered. '
                . 'Ensure it implements %s and is autoconfigured or tagged.',
                $serviceId,
                IdentityResolverInterface::class,
            ));
    }

    public function condition(string $serviceId): RateLimitConditionInterface
    {
        return $this->conditions[$serviceId]
            ?? throw new InvalidArgumentException(sprintf(
                'Rate limit condition service "%s" is not registered. '
                . 'Ensure it implements %s and is autoconfigured or tagged.',
                $serviceId,
                RateLimitConditionInterface::class,
            ));
    }

    /**
     * @template T of object
     *
     * @param iterable<T> $services
     *
     * @return array<string, T>
     */
    private function index(iterable $services): array
    {
        $indexed = [];

        foreach ($services as $serviceId => $service) {
            $indexed[$service::class] = $service;

            if (!is_string($serviceId)) {
                continue;
            }

            $indexed[$serviceId] = $service;
        }

        return $indexed;
    }
}
