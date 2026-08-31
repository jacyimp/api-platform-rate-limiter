<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Symfony\EventListener;

use ApiPlatform\Metadata\Operation;
use Jacyimp\ApiPlatformRateLimiter\ApiPlatform\RateLimitResolver;
use Jacyimp\ApiPlatformRateLimiter\Core\RateLimitEnforcer;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final readonly class ApiPlatformRateLimitListener
{
    public function __construct(
        private RateLimitResolver $rateLimitResolver,
        private RateLimitEnforcer $rateLimitEnforcer,
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

        throw new TooManyRequestsHttpException(
            retryAfter: $rejected
                ->result
                ->retryAfter
                ->format(DATE_RFC7231),
            message: 'Rate limit exceeded.',
            headers: [
                'RateLimit-Limit' => (string) $rejected
                    ->rateLimit
                    ->definition
                    ->limit,
                'RateLimit-Remaining' => (string) $rejected
                    ->result
                    ->remaining,
            ],
        );
    }
}
