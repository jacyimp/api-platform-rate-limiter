<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Core;

/**
 * @internal
 */
final class LimiterStorageKey
{
    public static function for(
        ResolvedRateLimit $rateLimit,
        string $identity,
    ): string {
        $definition = $rateLimit->definition;
        $components = [
            'v1',
            $rateLimit->bucket,
            $identity,
            $definition->policy->value,
            (string) $definition->limit,
            (string) $definition->intervalSeconds,
        ];
        $context = hash_init('sha256');

        foreach ($components as $component) {
            hash_update($context, sprintf('%d:', strlen($component)));
            hash_update($context, $component);
        }

        return hash_final($context);
    }

    private function __construct()
    {
    }
}
