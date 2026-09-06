<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\NoSecurity;

use JacyImp\ApiPlatformRateLimiter\Contract\IdentityResolverInterface;
use JacyImp\ApiPlatformRateLimiter\Symfony\ApiPlatformRateLimiterBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class NoSecurityKernel extends Kernel
{
    use MicroKernelTrait;

    /** @return iterable<\Symfony\Component\HttpKernel\Bundle\BundleInterface> */
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new ApiPlatformRateLimiterBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'no-security-test',
            'cache' => [
                'app' => 'cache.adapter.array',
            ],
        ]);

        $container
            ->services()
            ->set('no_security.controller', NoSecurityController::class)
            ->args([
                service(IdentityResolverInterface::class),
            ])
            ->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes
            ->add('identity', '/identity')
            ->controller('no_security.controller');
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/api-platform-rate-limiter-no-security/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/api-platform-rate-limiter-no-security/log';
    }
}
