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

    #[Test]
    public function itAcceptsAStringUserIdentifier(): void
    {
        $user = self::createStub(Authenticatable::class);
        $user->method('getAuthIdentifier')->willReturn('customer-42');
        $request = Request::create('/', server: ['REMOTE_ADDR' => '192.0.2.1']);
        $request->setUserResolver(static fn (): Authenticatable => $user);

        self::assertSame('user:customer-42', (new LaravelIdentityResolver($request))->resolve());
    }

    #[Test]
    public function itRejectsUnsupportedAndBlankUserIdentifiers(): void
    {
        $unsupported = new class implements \Stringable {
            public function __toString(): string
            {
                return 'object-identifier';
            }
        };

        foreach ([$unsupported, ' '] as $identifier) {
            $user = self::createStub(Authenticatable::class);
            $user->method('getAuthIdentifier')->willReturn($identifier);
            $request = Request::create('/', server: ['REMOTE_ADDR' => '192.0.2.1']);
            $request->setUserResolver(static fn (): Authenticatable => $user);

            self::assertSame('ip:192.0.2.1', (new LaravelIdentityResolver($request))->resolve());
        }
    }

    #[Test]
    public function itReturnsNullWithoutAUsableIdentity(): void
    {
        $request = new class extends Request {
            public function user(mixed $guard = null): mixed
            {
                return null;
            }

            public function ip(): mixed
            {
                return null;
            }
        };

        self::assertNull((new LaravelIdentityResolver($request))->resolve());

        $blankIpRequest = Request::create('/', server: ['REMOTE_ADDR' => ' ']);
        self::assertNull((new LaravelIdentityResolver($blankIpRequest))->resolve());
    }
}
