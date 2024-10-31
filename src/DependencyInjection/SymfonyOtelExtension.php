<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\DependencyInjection;

use Exception;
use Macpaw\SymfonyOtelBundle\Span\ExecutionTimeSpanTracer;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class SymfonyOtelExtension extends Extension
{
    public const NAME = 'otel_bundle';

    /**
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../Resources/config'));
        $loader->load('services.yml');

        $configuration = $this->getConfiguration($configs, $container);
        $configs = $this->processConfiguration($configuration, $configs);

        $definition = $container->getDefinition(ExecutionTimeSpanTracer::class);
        $definition->setArguments([
            '$tracerName' => $configs['tracer_name'],
        ]);

        $container->setDefinition(ExecutionTimeSpanTracer::class, $definition);

    }

    /**
     * @param array<string, mixed> $config
     */
    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return new Configuration();
    }
}
