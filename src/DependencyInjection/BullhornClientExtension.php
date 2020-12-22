<?php

namespace Developersnl\BullhornClientBundle\DependencyInjection;

use Developersnl\BullhornClientBundle\Client\AuthenticationClient;
use Developersnl\BullhornClientBundle\Client\RestClient;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

class BullhornClientExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $authentication = new Definition(AuthenticationClient::class);
        $authentication->setArgument('$clientId', $config['authentication']['clientId']);
        $authentication->setArgument('$clientSecret', $config['authentication']['clientSecret']);
        $authentication->setArgument('$authUrl', $config['authentication']['authUrl']);
        $authentication->setArgument('$tokenUrl', $config['authentication']['tokenUrl']);
        $authentication->setArgument('$loginUrl', $config['authentication']['loginUrl']);
        $authentication->setArgument('$cache', new Reference('cache'));

        $rest = new Definition(RestClient::class);
        $rest->setArgument('$authClient', new Reference('Developersnl\BullhornClientBundle\Client\AuthenticationClient'));
        $rest->setArgument('$cache', new Reference('cache'));
        $rest->setArgument('$username', $config['rest']['username']);
        $rest->setArgument('$password', $config['rest']['password']);

        $container->setDefinition('Developersnl\BullhornClientBundle\Client\AuthenticationClient', $authentication);
        $container->setDefinition('Developersnl\BullhornClientBundle\Client\RestClient', $rest);
    }
}