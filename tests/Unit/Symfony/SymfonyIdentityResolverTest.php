<?php

declare(strict_types=1);

namespace Jacyimp\ApiPlatformRateLimiter\Tests\Unit\Symfony;

use Jacyimp\ApiPlatformRateLimiter\Symfony\SymfonyIdentityResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[CoversClass(SymfonyIdentityResolver::class)]
final class SymfonyIdentityResolverTest extends TestCase
{
    #[Test]
    public function itResolvesAuthenticatedUser(): void
    {
        $user = self::createStub(UserInterface::class);
        $user
            ->method('getUserIdentifier')
            ->willReturn('jacy@example.com');

        $token = self::createStub(TokenInterface::class);
        $token
            ->method('getUser')
            ->willReturn($user);

        $tokenStorage = self::createStub(
            TokenStorageInterface::class,
        );
        $tokenStorage
            ->method('getToken')
            ->willReturn($token);

        $resolver = new SymfonyIdentityResolver(
            requestStack: new RequestStack(),
            tokenStorage: $tokenStorage,
        );

        self::assertSame(
            'user:jacy@example.com',
            $resolver->resolve(),
        );
    }

    #[Test]
    public function itFallsBackToClientIp(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(
            Request::create(
                uri: '/',
                server: [
                    'REMOTE_ADDR' => '192.0.2.1',
                ],
            ),
        );

        $tokenStorage = self::createStub(
            TokenStorageInterface::class,
        );

        $tokenStorage
            ->method('getToken')
            ->willReturn(null);

        $resolver = new SymfonyIdentityResolver(
            requestStack: $requestStack,
            tokenStorage: $tokenStorage,
        );

        self::assertSame(
            'ip:192.0.2.1',
            $resolver->resolve(),
        );
    }

    #[Test]
    public function itFallsBackToClientIpForAnonymousToken(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(
            Request::create(
                uri: '/',
                server: [
                    'REMOTE_ADDR' => '192.0.2.1',
                ],
            ),
        );

        $token = self::createStub(TokenInterface::class);
        $token
            ->method('getUser')
            ->willReturn(null);

        $tokenStorage = self::createStub(
            TokenStorageInterface::class,
        );
        $tokenStorage
            ->method('getToken')
            ->willReturn($token);

        $resolver = new SymfonyIdentityResolver(
            requestStack: $requestStack,
            tokenStorage: $tokenStorage,
        );

        self::assertSame(
            'ip:192.0.2.1',
            $resolver->resolve(),
        );
    }

    #[Test]
    public function itRejectsMissingRequestForAnonymousUser(): void
    {
        $tokenStorage = self::createStub(
            TokenStorageInterface::class,
        );

        $resolver = new SymfonyIdentityResolver(
            requestStack: new RequestStack(),
            tokenStorage: $tokenStorage,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Cannot resolve rate limit identity without a current request.',
        );

        $resolver->resolve();
    }

    #[Test]
    public function itRejectsEmptyAuthenticatedUserIdentifier(): void
    {
        $user = self::createStub(UserInterface::class);
        $user
            ->method('getUserIdentifier')
            ->willReturn('   ');

        $token = self::createStub(TokenInterface::class);
        $token
            ->method('getUser')
            ->willReturn($user);

        $tokenStorage = self::createStub(
            TokenStorageInterface::class,
        );
        $tokenStorage
            ->method('getToken')
            ->willReturn($token);

        $resolver = new SymfonyIdentityResolver(
            requestStack: new RequestStack(),
            tokenStorage: $tokenStorage,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Authenticated user identifier cannot be empty.',
        );

        $resolver->resolve();
    }
}
