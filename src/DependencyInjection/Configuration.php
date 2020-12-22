<?php

namespace Developersnl\BullhornClientBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder('bullhorn_client');

        $builder->getRootNode()
            ->children()
                ->arrayNode('authentication')
                    ->children()
                        ->scalarNode('clientId')->end()
                        ->scalarNode('clientSecret')->end()
                        ->scalarNode('authUrl')->end()
                        ->scalarNode('tokenUrl')->end()
                        ->scalarNode('loginUrl')->end()
                    ->end()
                ->end()
                ->arrayNode('rest')
                    ->children()
                        ->scalarNode('authClient')->end()
                        ->scalarNode('username')->end()
                        ->scalarNode('password')->end()
                    ->end()
                ->end()
            ->end();

        return $builder;
    }
}
