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
                ->arrayNode('instrumentations')
                    ->defaultValue([])
                    ->scalarPrototype()
                        ->cannotBeEmpty()
                    ->end()
                ->end()
            ->end();

        return $tree;
    }
}
