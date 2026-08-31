<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Symfony\EventListener;

use ApiPlatform\Metadata\Operation;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitResolver;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejection;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejectionHandlerInterface;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitEnforcer;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * @internal
 */
final readonly class ApiPlatformRateLimitListener
{
    public function __construct(
        private RateLimitResolver $rateLimitResolver,
        private RateLimitEnforcer $rateLimitEnforcer,
        private RateLimitRejectionHandlerInterface $rejectionHandler,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $operation = $event
            ->getRequest()
            ->attributes
            ->get('_api_operation');

        if (!$operation instanceof Operation) {
            return;
        }

        $rateLimits = $this->rateLimitResolver->resolve(
            operation: $operation,
            operationKey: $operation->getName() ?? '',
        );

        $enforcement = $this->rateLimitEnforcer->enforce(
            $rateLimits,
        );

        $rejected = $enforcement->rejectedConsumption();

        if ($rejected === null) {
            return;
        }

        $this->rejectionHandler->reject(
            new RateLimitRejection(
                limit: $rejected->rateLimit->definition->limit,
                remaining: $rejected->result->remaining,
                retryAfter: $rejected->result->retryAfter,
            ),
        );
    }
}
