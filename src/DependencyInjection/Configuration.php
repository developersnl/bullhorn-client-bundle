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
                        ->scalarNode('client_id')->end()
                        ->scalarNode('client_secret')->end()
                        ->scalarNode('auth_url')->end()
                        ->scalarNode('token_url')->end()
                        ->scalarNode('login_url')->end()
                        ->scalarNode('cache')->end()
                    ->end()
                ->end()
                ->arrayNode('rest')
                    ->children()
                        ->scalarNode('authClient')->end()
                        ->scalarNode('cache')->end()
                        ->scalarNode('username')->end()
                        ->scalarNode('password')->end()
                    ->end()
                ->end()
            ->end();

        return $builder;
    }
}
