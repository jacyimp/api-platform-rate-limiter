<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Laravel\Middleware;

use ApiPlatform\Metadata\Operation;
use Closure;
use Illuminate\Http\Request;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitResolver;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejection;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejectionHandlerInterface;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitEnforcer;
use Symfony\Component\HttpFoundation\Response;

/** @internal */
final readonly class ApiPlatformRateLimitMiddleware
{
    public function __construct(
        private RateLimitResolver $rateLimitResolver,
        private RateLimitEnforcer $rateLimitEnforcer,
        private RateLimitRejectionHandlerInterface $rejectionHandler,
    ) {
    }

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $operation = $request->attributes->get('_api_operation');

        if (!$operation instanceof Operation) {
            return $next($request);
        }

        $rateLimits = $this->rateLimitResolver->resolve(
            operation: $operation,
            operationKey: $operation->getName() ?? '',
        );
        $rejected = $this->rateLimitEnforcer
            ->enforce($rateLimits)
            ->rejectedConsumption();

        if ($rejected === null) {
            return $next($request);
        }

        $this->rejectionHandler->reject(new RateLimitRejection(
            limit: $rejected->rateLimit->definition->limit,
            remaining: $rejected->result->remaining,
            retryAfter: $rejected->result->retryAfter,
        ));
    }
}
