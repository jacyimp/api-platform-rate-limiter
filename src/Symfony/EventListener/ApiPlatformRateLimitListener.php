<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Symfony\EventListener;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use JacyImp\ApiPlatformRateLimiter\ApiPlatform\RateLimitResolver;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejection;
use JacyImp\ApiPlatformRateLimiter\Contract\RateLimitRejectionHandlerInterface;
use JacyImp\ApiPlatformRateLimiter\Core\RateLimitEnforcer;
use Symfony\Component\HttpFoundation\Request;
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
        private ?ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory = null,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $operation = $this->resolveOperation($event->getRequest());

        if ($operation === null) {
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

    private function resolveOperation(Request $request): ?Operation
    {
        $operation = $request->attributes->get('_api_operation');

        if ($operation instanceof Operation) {
            return $operation;
        }

        $resourceClass = $request->attributes->get('_api_resource_class');
        $operationName = $request->attributes->get('_api_operation_name');

        if (
            $this->resourceMetadataCollectionFactory === null
            || !is_string($resourceClass)
            || $resourceClass === ''
            || !is_string($operationName)
            || $operationName === ''
        ) {
            return null;
        }

        $operation = $this
            ->resourceMetadataCollectionFactory
            ->create($resourceClass)
            ->getOperation($operationName);

        // Make it available to anything running after us too.
        $request->attributes->set('_api_operation', $operation);

        return $operation;
    }
}
