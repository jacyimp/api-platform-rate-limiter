<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Symfony;

use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Exception\IdentityResolutionException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @internal
 */
final readonly class SymfonyIdentityResolver implements IdentityResolverInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private ?TokenStorageInterface $tokenStorage = null,
    ) {
    }

    public function resolve(): string
    {
        $user = $this->tokenStorage
            ?->getToken()
            ?->getUser();

        if ($user instanceof UserInterface) {
            $identifier = trim($user->getUserIdentifier());

            if ($identifier === '') {
                throw new IdentityResolutionException(
                    'Authenticated user identifier cannot be empty.',
                );
            }

            return sprintf(
                'user:%s',
                $identifier,
            );
        }

        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            throw new IdentityResolutionException(
                'Cannot resolve rate limit identity without a current request.',
            );
        }

        $ip = $request->getClientIp();

        if ($ip === null) {
            throw new IdentityResolutionException(
                'Cannot resolve rate limit identity without a client IP.',
            );
        }

        return sprintf(
            'ip:%s',
            $ip,
        );
    }
}
