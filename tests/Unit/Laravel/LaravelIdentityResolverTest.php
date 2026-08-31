<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Unit\Laravel;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use JacyImp\ApiPlatformRateLimiter\Laravel\LaravelIdentityResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LaravelIdentityResolver::class)]
final class LaravelIdentityResolverTest extends TestCase
{
    #[Test]
    public function itPrefersTheAuthenticatedUserIdentifier(): void
    {
        $user = self::createStub(Authenticatable::class);
        $user->method('getAuthIdentifier')->willReturn(42);
        $request = Request::create('/', server: ['REMOTE_ADDR' => '192.0.2.1']);
        $request->setUserResolver(static fn (): Authenticatable => $user);

        self::assertSame('user:42', (new LaravelIdentityResolver($request))->resolve());
    }

    #[Test]
    public function itFallsBackToTheRequestIp(): void
    {
        $request = Request::create('/', server: ['REMOTE_ADDR' => '192.0.2.1']);

        self::assertSame('ip:192.0.2.1', (new LaravelIdentityResolver($request))->resolve());
    }
}
