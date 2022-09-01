<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\DependencyInjection\Compiler;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\ApiPlatformException;
use ReflectionClass;
use Symfony\Component\Config\Resource\ReflectionClassResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ApiPlatformEventEnginePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->addResource(
            // TODO can we remove this one? since it's not more used as a type?
            new ReflectionClassResource(
                new ReflectionClass(ApiPlatformException::class)
            )
        );
    }
}
