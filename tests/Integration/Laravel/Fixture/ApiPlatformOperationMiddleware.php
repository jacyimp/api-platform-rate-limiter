<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Laravel\Fixture;

use ApiPlatform\Metadata\Get;
use Closure;
use Illuminate\Http\Request;
use JacyImp\ApiPlatformRateLimiter\Metadata\BypassRateLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\AllOf;
use JacyImp\ApiPlatformRateLimiter\Metadata\Condition\Condition;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicBucket;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicCost;
use JacyImp\ApiPlatformRateLimiter\Metadata\DynamicLimit;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\CompositeIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\FirstAvailableIdentity;
use JacyImp\ApiPlatformRateLimiter\Metadata\Identity\Identity;
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
            'configured' => [RateLimit::class => new RateLimit(bucket: 'configured')],
            'dynamic-limit' => [RateLimit::class => new RateLimit(
                limit: new DynamicLimit(FixedLimit::class),
                interval: '1 minute',
            )],
            'dynamic-bucket' => [RateLimit::class => new RateLimit(
                bucket: new DynamicBucket(FixedBucket::class),
            )],
            'dynamic-cost' => [RateLimit::class => new RateLimit(
                limit: 2,
                interval: '1 minute',
                cost: new DynamicCost(FixedCost::class),
            )],
            'composite' => [RateLimit::class => new RateLimit(
                limit: 1,
                interval: '1 minute',
                identity: new CompositeIdentity([
                    new Identity(PrimaryIdentity::class),
                    new Identity(SecondaryIdentity::class),
                ]),
            )],
            'fallback' => [RateLimit::class => new RateLimit(
                limit: 1,
                interval: '1 minute',
                identity: new FirstAvailableIdentity([
                    new Identity(MissingIdentity::class),
                    new Identity(SecondaryIdentity::class),
                ]),
            )],
            'condition' => [RateLimit::class => new RateLimit(
                limit: 1,
                interval: '1 minute',
                when: new AllOf([
                    new Condition(Applies::class),
                    new Condition(DoesNotApply::class),
                ]),
            )],
            'declarative-bypass' => [
                RateLimit::class => new RateLimit(limit: 1, interval: '1 minute'),
                BypassRateLimit::class => new BypassRateLimit(),
            ],
            default => [RateLimit::class => new RateLimit(limit: 1, interval: '1 minute')],
        };

        $request->attributes->set('_api_operation', new Get(
            name: $scenario,
            extraProperties: $extraProperties,
        ));

        return $next($request);
    }
}
