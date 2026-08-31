<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Laravel;

use Illuminate\Contracts\Cache\Repository;
use Symfony\Component\RateLimiter\LimiterStateInterface;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/** @internal */
final readonly class LaravelCacheStorage implements StorageInterface
{
    public function __construct(private Repository $cache)
    {
    }

    public function save(LimiterStateInterface $limiterState): void
    {
        $expiration = $limiterState->getExpirationTime();

        $this->cache->put(
            $this->key($limiterState->getId()),
            $limiterState,
            $expiration,
        );
    }

    public function fetch(string $limiterStateId): ?LimiterStateInterface
    {
        $state = $this->cache->get($this->key($limiterStateId));

        return $state instanceof LimiterStateInterface ? $state : null;
    }

    public function delete(string $limiterStateId): void
    {
        $this->cache->forget($this->key($limiterStateId));
    }

    private function key(string $limiterStateId): string
    {
        return sprintf('api-platform-rate-limiter:%s', sha1($limiterStateId));
    }
}
