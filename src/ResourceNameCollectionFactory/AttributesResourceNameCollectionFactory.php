<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceNameCollectionFactory;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\Operation as GraphQlOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use ApiPlatform\Metadata\Util\ReflectionClassRecursiveIterator;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

use function array_keys;
use function assert;

/**
 * We need to overwrite this class because we don't want single http operations be seen as a resource.
 */
#[AsAlias('api_platform.metadata.resource.name_collection_factory.attributes')]
#[AsDecorator('api_platform.metadata.resource.name_collection_factory')]
class AttributesResourceNameCollectionFactory implements ResourceNameCollectionFactoryInterface
{
    /** @param array<string> $paths */
    public function __construct(
        #[Autowire('%api_platform.resource_class_directories%')]
        private readonly array $paths,
        #[AutowireDecorated]
        private readonly ResourceNameCollectionFactoryInterface|null $decorated = null,
    ) {
    }

    public function create(): ResourceNameCollection
    {
        /** @var array<string, bool> $classes */
        $classes = [];

        if ($this->decorated) {
            foreach ($this->decorated->create() as $resourceClass) {
                $classes[$resourceClass] = true;
            }
        }

        /** @var array<string, ReflectionClass<object>> $reflectionClasses */
        $reflectionClasses = ReflectionClassRecursiveIterator::getReflectionClassesFromDirectories($this->paths);
        foreach ($reflectionClasses as $className => $reflectionClass) {
            assert($reflectionClass instanceof ReflectionClass);
            if (! $this->isResource($reflectionClass)) {
                continue;
            }

            $classes[$className] = true;
        }

        return new ResourceNameCollection(array_keys($classes));
    }

    /** @param ReflectionClass<object> $reflectionClass */
    private function isResource(ReflectionClass $reflectionClass): bool
    {
        if ($reflectionClass->getAttributes(ApiResource::class, ReflectionAttribute::IS_INSTANCEOF)) {
            return true;
        }

        return (bool) $reflectionClass->getAttributes(GraphQlOperation::class, ReflectionAttribute::IS_INSTANCEOF);
    }
}
