<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder(SymfonyOtelExtension::NAME);
        $rootNode = $tree->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('service_name')
                    ->cannotBeEmpty()
                    ->defaultValue('symfony-app')
                ->end()
                ->scalarNode('tracer_name')
                    ->cannotBeEmpty()
                    ->defaultValue('symfony-tracer')
                ->end()
            ->booleanNode('force_flush_on_terminate')
            ->info(
                'If true, calls tracer provider forceFlush() on Kernel terminate; default false to preserve BatchSpanProcessor async export.',
            )
            ->defaultFalse()
            ->end()
            ->integerNode('force_flush_timeout_ms')
            ->info('Timeout in milliseconds for tracer provider forceFlush() when enabled (non-destructive flush).')
            ->min(0)
            ->defaultValue(100)
            ->end()
                ->arrayNode('instrumentations')
                    ->defaultValue([])
                    ->scalarPrototype()
                        ->cannotBeEmpty()
                    ->end()
                ->end()
                ->arrayNode('header_mappings')
                    ->defaultValue([
                        'http.request_id' => 'X-Request-Id',
                    ])
                    ->info('Map span attribute names to HTTP header names')
                    ->example([
                        'http.request_id' => 'X-Request-Id',
                    ])
                    ->scalarPrototype()
                        ->cannotBeEmpty()
            ->end()
            ->end()
            ->arrayNode('logging')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('enable_trace_processor')
            ->info('Enable Monolog processor that injects trace_id/span_id into log records context')
            ->defaultTrue()
            ->end()
            ->arrayNode('log_keys')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('trace_id')->defaultValue('trace_id')->end()
            ->scalarNode('span_id')->defaultValue('span_id')->end()
            ->scalarNode('trace_flags')->defaultValue('trace_flags')->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->arrayNode('metrics')
            ->addDefaultsIfNotSet()
            ->children()
            ->arrayNode('request_counters')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('enabled')->defaultFalse()->end()
            ->enumNode('backend')
            ->values(['otel', 'event'])
            ->defaultValue('otel')
            ->end()
            ->end()
            ->end()
                    ->end()
                ->end()
            ->end();

        return $tree;
    }
}
