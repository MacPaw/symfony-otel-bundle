<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\DependencyInjection;

use Exception;
use Macpaw\SymfonyOtelBundle\Instrumentation\Utils\RouterUtils;
use Macpaw\SymfonyOtelBundle\Listeners\RequestCountersEventSubscriber;
use Macpaw\SymfonyOtelBundle\Logging\MonologTraceContextProcessor;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class SymfonyOtelExtension extends Extension
{
    public const NAME = 'otel_bundle';

    /**
     * @param array<int, array<string, mixed>> $configs
     *
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../Resources/config'));
        $loader->load('services.yml');

        $configuration = $this->getConfiguration($configs, $container);
        $configs = $this->processConfiguration($configuration, $configs);

        /** @var string $serviceName */
        $serviceName = $configs['service_name'];
        /** @var string $tracerName */
        $tracerName = $configs['tracer_name'];
        /** @var bool $forceFlushOnTerminate */
        $forceFlushOnTerminate = $configs['force_flush_on_terminate'];
        /** @var int $forceFlushTimeoutMs */
        $forceFlushTimeoutMs = $configs['force_flush_timeout_ms'];
        /** @var array<int, string> $instrumentations */
        $instrumentations = $configs['instrumentations'];
        /** @var array<string, string> $headerMappings */
        $headerMappings = $configs['header_mappings'];

        // Logging config
        /** @var array{enable_trace_processor: bool, log_keys: array{trace_id:string, span_id:string, trace_flags:string}} $logging */
        $logging = $configs['logging'] ?? [
            'enable_trace_processor' => true,
            'log_keys' => ['trace_id' => 'trace_id', 'span_id' => 'span_id', 'trace_flags' => 'trace_flags'],
        ];

        // Metrics config
        /** @var array{request_counters: array{enabled: bool, backend: string}} $metrics */
        $metrics = $configs['metrics'] ?? ['request_counters' => ['enabled' => false, 'backend' => 'otel']];

        $container->setParameter('otel_bundle.service_name', $serviceName);
        $container->setParameter('otel_bundle.tracer_name', $tracerName);
        $container->setParameter('otel_bundle.force_flush_on_terminate', $forceFlushOnTerminate);
        $container->setParameter('otel_bundle.force_flush_timeout_ms', $forceFlushTimeoutMs);
        $container->setParameter('otel_bundle.instrumentations', $instrumentations);
        $container->setParameter('otel_bundle.header_mappings', $headerMappings);
        $container->setParameter('otel_bundle.logging.log_keys', $logging['log_keys']);
        $container->setParameter(
            'otel_bundle.logging.enable_trace_processor',
            (bool)$logging['enable_trace_processor'],
        );
        $container->setParameter(
            'otel_bundle.metrics.request_counters.enabled',
            (bool)$metrics['request_counters']['enabled'],
        );
        $container->setParameter(
            'otel_bundle.metrics.request_counters.backend',
            (string)$metrics['request_counters']['backend'],
        );

        // Conditionally register Monolog trace context processor
        if ($container->hasParameter('otel_bundle.logging.enable_trace_processor')
            && $container->getParameter('otel_bundle.logging.enable_trace_processor') === true
        ) {
            $def = new Definition(MonologTraceContextProcessor::class);
            $def->setArgument(0, '%otel_bundle.logging.log_keys%');
            $def->addTag('monolog.processor');
            $container->setDefinition(MonologTraceContextProcessor::class, $def);
        }

        // Conditionally register request counters subscriber
        $enabledCounters = (bool)$container->getParameter('otel_bundle.metrics.request_counters.enabled');
        if ($enabledCounters) {
            $backend = (string)$container->getParameter('otel_bundle.metrics.request_counters.backend');
            $def = new Definition(RequestCountersEventSubscriber::class, [
                new Reference(MeterProviderInterface::class),
                new Reference(RouterUtils::class),
                new Reference(InstrumentationRegistry::class),
                $backend,
            ]);
            $def->addTag('kernel.event_subscriber');
            $container->setDefinition(RequestCountersEventSubscriber::class, $def);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $config
     */
    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return new Configuration();
    }

    public function getAlias(): string
    {
        return self::NAME;
    }
}
