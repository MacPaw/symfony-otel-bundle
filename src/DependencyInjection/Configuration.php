<?php

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
                ->scalarNode('tracer_name')
                    ->cannotBeEmpty()
                    ->defaultValue('test-template')
                ->end()
                ->scalarNode('service_name')
                    ->cannotBeEmpty()
                    ->defaultValue('test-service')
                ->end()
                ->arrayNode('span_tracers')
                ->defaultValue([])
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('class')->isRequired()->end()
                            ->scalarNode('tag')->isRequired()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $tree;
    }
}
