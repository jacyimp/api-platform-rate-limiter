<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture;

use ApiPlatform\Metadata\Get;
use Closure;
use Illuminate\Http\Request;
use JacyImp\ApiPlatformRateLimiter\Metadata\BypassRateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AllOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicBucket;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\CompositeIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\FirstAvailableIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;
use Symfony\Component\HttpFoundation\Response;

final class ApiPlatformOperationMiddleware
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $scenario = $request->route('scenario');
        if (!is_string($scenario)) {
            return $next($request);
        }
        $extraProperties = match ($scenario) {
            'plain' => [],
            'configured' => [new RateLimit(bucket: 'configured')],
            'dynamic-limit' => [new RateLimit(
                limit: FixedLimit::class,
                interval: '1 minute',
            )],
            'dynamic-bucket' => [new RateLimit(
                bucket: new DynamicBucket(FixedBucket::class),
            )],
            'dynamic-cost' => [new RateLimit(
                limit: 2,
                interval: '1 minute',
                cost: FixedCost::class,
            )],
            'composite' => [new RateLimit(
                limit: 1,
                interval: '1 minute',
                identity: new CompositeIdentity([
                    PrimaryIdentity::class,
                    SecondaryIdentity::class,
                ]),
            )],
            'fallback' => [new RateLimit(
                limit: 1,
                interval: '1 minute',
                identity: new FirstAvailableIdentity([
                    MissingIdentity::class,
                    SecondaryIdentity::class,
                ]),
            )],
            'condition' => [new RateLimit(
                limit: 1,
                interval: '1 minute',
                when: new AllOf([
                    Applies::class,
                    DoesNotApply::class,
                ]),
            )],
            'declarative-bypass' => [
                new RateLimit(limit: 1, interval: '1 minute'),
                new BypassRateLimit(),
            ],
            default => [new RateLimit(limit: 1, interval: '1 minute')],
        };

        $request->attributes->set('_api_operation', new Get(
            name: $scenario,
            extraProperties: $extraProperties,
        ));

        return $next($request);
    }
}
