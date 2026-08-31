<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\Tests\Integration\Symfony\Fixture;

use JacyImp\ApiPlatformRateLimiter\Symfony\ApiPlatformRateLimiterBundle;
use JacyImp\ApiPlatformRateLimiter\Symfony\DependencyInjection\ApiPlatformRateLimiterExtension;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, array<string, int|string>> $globals
     */
    public function __construct(
        string $environment,
        bool $debug,
        private readonly array $globals = [],
    ) {
        parent::__construct($environment, $debug);
    }

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

        $rateLimiterConfig = [
            'buckets' => [
                'shared_api' => [
                    'limit' => 1,
                    'interval' => '1 minute',
                    'policy' => 'sliding_window',
                ],
                'conditional_shared' => [
                    'limit' => 1,
                    'interval' => '1 minute',
                    'identity_resolver' => FixedIdentityResolver::class,
                    'when' => 'test.never_apply',
                ],
                'weighted_shared' => [
                    'limit' => 5,
                    'interval' => '1 minute',
                ],
            ],
        ];

        if ($this->globals !== []) {
            $rateLimiterConfig['globals'] = $this->globals;
        }

        $container->extension(
            'api_platform_rate_limiter',
            $rateLimiterConfig,
        );

        $services = $container->services();

        $services
            ->set(LimitedController::class)
            ->public();

        $services
            ->set(ManualRateLimitProvider::class)
            ->tag(
                ApiPlatformRateLimiterExtension::PROVIDER_TAG,
            );

        $services
            ->set(FixedIdentityResolver::class)
            ->autoconfigure();

        $services
            ->set(FixedCostResolver::class)
            ->autoconfigure();

        $services
            ->set('test.never_apply', NeverApplyCondition::class)
            ->tag(
                ApiPlatformRateLimiterExtension::CONDITION_TAG,
            );
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
            $this->getEnvironment(),
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
