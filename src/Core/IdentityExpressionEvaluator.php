<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Core;

use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\CompositeIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\FirstAvailableIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\IdentityExpression;

/** @internal */
final readonly class IdentityExpressionEvaluator
{
    public function __construct(private RateLimitStrategyRegistry $strategyRegistry)
    {
    }

    public function evaluate(string|IdentityExpression $expression): ?string
    {
        if (is_string($expression)) {
            return $this->strategyRegistry
                ->identityResolver($expression)
                ->resolve();
        }

        if ($expression instanceof FirstAvailableIdentity) {
            foreach ($expression->identities as $identity) {
                $resolved = $this->evaluate($identity);

                if ($resolved !== null) {
                    return $resolved;
                }
            }

            return null;
        }

        if (!$expression instanceof CompositeIdentity) {
            throw new \LogicException(sprintf(
                'Unsupported identity expression "%s".',
                $expression::class,
            ));
        }

        $components = [];

        foreach ($expression->identities as $identity) {
            $resolved = $this->evaluate($identity);

            if ($resolved === null) {
                return null;
            }

            $components[] = $resolved;
        }

        return 'composite:' . implode('', array_map(
            static fn (string $component): string => sprintf(
                '%d:%s',
                strlen($component),
                $component,
            ),
            $components,
        ));
    }
}
