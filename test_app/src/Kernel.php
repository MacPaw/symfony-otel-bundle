<?php

declare(strict_types=1);

namespace App;

use Macpaw\SymfonyOtelBundle\SymfonyOtelBundle;
use OpenTelemetry\Contrib\Symfony\OtelBundle\OtelBundle;
use OpenTelemetry\Contrib\Symfony\OtelSdkBundle\OtelSdkBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
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

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import('../config/services.yaml');

        $container->extension('framework', [
            'secret' => 'test-secret-key',
            'router' => [
                'utf8' => true,
            ],
        ]);

        $container->extension('open_telemetry', [
            'resource' => [
                'service' => [
                    'name' => '%env(OTEL_SERVICE_NAME)%',
                    'version' => '1.0.0',
                ],
            ],
            'tracing' => [
                'enabled' => true,
                'exporter' => [
                    'otlp' => [
                        'endpoint' => '%env(OTEL_EXPORTER_OTLP_ENDPOINT)%',
                        'protocol' => '%env(OTEL_EXPORTER_OTLP_PROTOCOL)%',
                    ],
                ],
            ],
        ]);

        $container->extension('otel_bundle', [
            'tracer_name' => '%env(OTEL_TRACER_NAME)%',
            'service_name' => '%env(OTEL_SERVICE_NAME)%',
            'span_tracers' => [
                [
                    'class' => 'Macpaw\SymfonyOtelBundle\Span\ExecutionTimeSpanTracer',
                    'tag' => 'kernel.event_subscriber',
                ],
            ],
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('../config/routes.yaml');
    }
} 
