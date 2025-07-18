<?php

declare(strict_types=1);

namespace App;

use Macpaw\SymfonyOtelBundle\SymfonyOtelBundle;
use OpenTelemetry\Contrib\Symfony\OtelBundle\OtelBundle;
use OpenTelemetry\Contrib\Symfony\OtelSdkBundle\OtelSdkBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new OtelBundle(),
            new OtelSdkBundle(),
            new SymfonyOtelBundle(),
        ];
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        putenv('APP_DEBUG=true');
        $_ENV['APP_DEBUG'] = $_SERVER['APP_DEBUG'] = true;

        $container->import(__DIR__ . '/../../Resources/config/otel_bundle.yml');
        $container->import(__DIR__ . '/../../Resources/config/services.yml');
        $container->import('../config/services.yaml');
        $container->import('../config/packages/otel_bundle.yml');

        $container->extension('framework', [
            'secret' => 'test-secret-key',
            'router' => [
                'utf8' => true,
            ],
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('../config/routes.yaml');
    }
}
