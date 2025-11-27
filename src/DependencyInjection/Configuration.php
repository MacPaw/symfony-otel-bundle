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
            ->end();

        return $tree;
    }
}
