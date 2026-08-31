<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Core;

use JacyImp\ApiPlatformRateLimiter\Contract\BucketResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\LimitResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitConditionInterface;
use JacyImp\ApiPlatformRateLimiter\Exception\InvalidRateLimitException;

/**
 * @internal
 */
final readonly class RateLimitStrategyRegistry
{
    /** @var array<string, IdentityResolverInterface> */
    private array $identityResolvers;

    /** @var array<string, RateLimitConditionInterface> */
    private array $conditions;
    /** @var array<string, BucketResolverInterface> */
    private array $bucketResolvers;

    /** @var array<string, LimitResolverInterface> */
    private array $limitResolvers;

    /**
     * @param iterable<IdentityResolverInterface> $identityResolvers
     * @param iterable<RateLimitConditionInterface> $conditions
     * @param iterable<BucketResolverInterface> $bucketResolvers
     * @param iterable<LimitResolverInterface> $limitResolvers
     */
    public function __construct(
        iterable $identityResolvers,
        iterable $conditions,
        iterable $bucketResolvers = [],
        iterable $limitResolvers = [],
    ) {
        $this->identityResolvers = $this->index($identityResolvers);
        $this->conditions = $this->index($conditions);
        $this->bucketResolvers = $this->index($bucketResolvers);
        $this->limitResolvers = $this->index($limitResolvers);
    }

    public function bucketResolver(string $serviceId): BucketResolverInterface
    {
        return $this->bucketResolvers[$serviceId]
            ?? throw new InvalidRateLimitException(sprintf(
                'Bucket resolver service "%s" is not registered. '
                . 'Ensure it implements %s and is autoconfigured or tagged.',
                $serviceId,
                BucketResolverInterface::class,
            ));
    }

    public function limitResolver(string $serviceId): LimitResolverInterface
    {
        return $this->limitResolvers[$serviceId]
            ?? throw new InvalidRateLimitException(sprintf(
                'Limit resolver service "%s" is not registered. '
                . 'Ensure it implements %s and is autoconfigured or tagged.',
                $serviceId,
                LimitResolverInterface::class,
            ));
    }

    public function identityResolver(
        string $serviceId,
    ): IdentityResolverInterface {
        return $this->identityResolvers[$serviceId]
            ?? throw new InvalidRateLimitException(sprintf(
                'Identity resolver service "%s" is not registered. '
                . 'Ensure it implements %s and is autoconfigured or tagged.',
                $serviceId,
                IdentityResolverInterface::class,
            ));
    }

    public function condition(string $serviceId): RateLimitConditionInterface
    {
        return $this->conditions[$serviceId]
            ?? throw new InvalidRateLimitException(sprintf(
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
