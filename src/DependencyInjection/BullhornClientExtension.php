<?php

namespace Developersnl\BullhornClientBundle\DependencyInjection;

use Developersnl\BullhornClientBundle\Client\AuthenticationClient;
use Developersnl\BullhornClientBundle\Client\RestClient;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;

class BullhornClientExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $authentication = new Definition(AuthenticationClient::class, $config['authentication']);
        $rest = new Definition(RestClient::class, $config['rest']);

        $container->setDefinition('developersnl.bullhorn_client.authentication', $authentication);
        $container->setDefinition('developersnl.bullhorn_client.rest', $rest);
    }
}