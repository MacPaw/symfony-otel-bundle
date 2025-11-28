<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\DependencyInjection;

use Monolog\LogRecord;
use Exception;
use Macpaw\SymfonyOtelBundle\Instrumentation\Utils\RouterUtils;
use Macpaw\SymfonyOtelBundle\Listeners\RequestCountersEventSubscriber;
use Macpaw\SymfonyOtelBundle\Logging\MonologTraceContextProcessor;
use Macpaw\SymfonyOtelBundle\Logging\MonologTraceContextProcessorV3;
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

        /** @var bool $enabled */
        $enabled = $configs['enabled'] ?? true;
        $envEnabled = getenv('OTEL_ENABLED');
        if ($envEnabled !== false && $envEnabled !== '') {
            $normalized = strtolower((string)$envEnabled);
            if (in_array($normalized, ['0', 'false', 'off', 'no'], true)) {
                $enabled = false;
            } elseif (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
                $enabled = true;
            }
        }
        /** @var string $serviceName */
        $serviceName = $configs['service_name'];
        /** @var string $tracerName */
        $tracerName = $configs['tracer_name'];
        /** @var bool $forceFlushOnTerminate */
        $forceFlushOnTerminate = $configs['force_flush_on_terminate'];
        /** @var int $forceFlushTimeoutMs */
        $forceFlushTimeoutMs = $configs['force_flush_timeout_ms'];
        /** @var array{preset:string,ratio:float,route_prefixes:array<int,string>} $sampling */
        $sampling = $configs['sampling'] ?? ['preset' => 'none', 'ratio' => 0.1, 'route_prefixes' => []];
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

        $container->setParameter('otel_bundle.enabled', $enabled);
        $container->setParameter('otel_bundle.service_name', $serviceName);
        $container->setParameter('otel_bundle.tracer_name', $tracerName);
        $container->setParameter('otel_bundle.force_flush_on_terminate', $forceFlushOnTerminate);
        $container->setParameter('otel_bundle.force_flush_timeout_ms', $forceFlushTimeoutMs);
        $container->setParameter('otel_bundle.sampling.preset', (string)$sampling['preset']);
        $container->setParameter('otel_bundle.sampling.ratio', (float)$sampling['ratio']);
        $container->setParameter('otel_bundle.sampling.route_prefixes', (array)$sampling['route_prefixes']);
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

        // Apply sampler preset only if not already defined via environment variables
        if ($enabled) {
            $envSampler = getenv('OTEL_TRACES_SAMPLER');
            if ($envSampler === false || $envSampler === '') {
                $preset = (string)$sampling['preset'];
                if ($preset === 'always_on') {
                    putenv('OTEL_TRACES_SAMPLER=always_on');
                } elseif ($preset === 'parentbased_ratio') {
                    putenv('OTEL_TRACES_SAMPLER=parentbased_traceidratio');
                    $ratio = (string)($sampling['ratio'] ?? '0.1');
                    if ((getenv('OTEL_TRACES_SAMPLER_ARG') === false) || getenv('OTEL_TRACES_SAMPLER_ARG') === '') {
                        putenv('OTEL_TRACES_SAMPLER_ARG=' . $ratio);
                    }
                }
            }
        }

        // Conditionally register Monolog trace context processor
        if (
            $enabled && $container->hasParameter('otel_bundle.logging.enable_trace_processor')
            && $container->getParameter('otel_bundle.logging.enable_trace_processor') === true
        ) {
            // Detect Monolog major version by presence of LogRecord (Monolog 3)
            $processorClass = class_exists(LogRecord::class)
                ? MonologTraceContextProcessorV3::class
                : MonologTraceContextProcessor::class;

            // Keep service id stable for BC: MonologTraceContextProcessor::class
            $def = new Definition($processorClass);
            $def->setArgument(0, '%otel_bundle.logging.log_keys%');
            $def->addTag('monolog.processor');
            $container->setDefinition(MonologTraceContextProcessor::class, $def);
        }

        // Conditionally register request counters subscriber
        $enabledCounters = $enabled && (bool)$container->getParameter('otel_bundle.metrics.request_counters.enabled');
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
