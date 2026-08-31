<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Symfony\Fixture;

use JacyImp\ApiPlatformRateLimiter\Symfony\ApiPlatformRateLimiterBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @return iterable<\Symfony\Component\HttpKernel\Bundle\BundleInterface>
     */
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new ApiPlatformRateLimiterBundle();
    }

    protected function configureContainer(
        ContainerConfigurator $container,
    ): void {
        $container->extension(
            'framework',
            [
                'secret' => 'test',
                'cache' => [
                    'app' => 'cache.adapter.filesystem',
                ],
            ],
        );

        $container->extension(
            'api_platform_rate_limiter',
            [
                'shared_buckets' => [
                    'shared_api' => [
                        'limit' => 1,
                        'interval' => '1 minute',
                        'policy' => 'sliding_window',
                    ],
                ],
            ],
        );

        $container
            ->services()
            ->set(LimitedController::class)
            ->public();
    }

    protected function configureRoutes(
        RoutingConfigurator $routes,
    ): void {
        $routes
            ->add(
                'limited',
                '/limited',
            )
            ->controller(
                LimitedController::class,
            );
    }

    public function getCacheDir(): string
    {
        return sprintf(
            '%s/jacyimp-api-platform-rate-limiter/cache/%s',
            sys_get_temp_dir(),
            $this->environment,
        );
    }

    public function getLogDir(): string
    {
        return sprintf(
            '%s/jacyimp-api-platform-rate-limiter/log',
            sys_get_temp_dir(),
        );
    }
}
