<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Core;

use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\IdentityExpression;

/** @internal */
final readonly class ExpressionIdentityResolver implements IdentityResolverInterface
{
    public function __construct(
        private IdentityExpressionEvaluator $evaluator,
        private IdentityExpression $expression,
    ) {
    }

    public function resolve(): ?string
    {
        return $this->evaluator->evaluate($this->expression);
    }
}
