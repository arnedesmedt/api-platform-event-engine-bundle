<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class ADSApiPlatformEventEngineExtension extends Extension
{
    /**
     * @param array<int, array<string, array<string, array<mixed>>>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );

        $loader->load('api_platform_event_engine.yaml');

        $container->setParameter(
            'api_platform_event_engine.open_api.servers',
            $configs[0]['open_api']['servers']
        );

        $container->setParameter(
            'api_platform_event_engine.open_api.tags',
            $configs[0]['open_api']['tags']
        );
    }
}
