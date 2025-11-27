<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\DependencyInjection;

use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

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

        $container->setParameter('otel_bundle.service_name', $serviceName);
        $container->setParameter('otel_bundle.tracer_name', $tracerName);
        $container->setParameter('otel_bundle.force_flush_on_terminate', $forceFlushOnTerminate);
        $container->setParameter('otel_bundle.force_flush_timeout_ms', $forceFlushTimeoutMs);
        $container->setParameter('otel_bundle.instrumentations', $instrumentations);
        $container->setParameter('otel_bundle.header_mappings', $headerMappings);
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
