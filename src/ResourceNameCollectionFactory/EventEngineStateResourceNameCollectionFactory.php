<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceNameCollectionFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Attribute\EventEngineState;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use ApiPlatform\Metadata\Util\ReflectionClassRecursiveIterator;
use ReflectionClass;

use function array_combine;
use function array_fill;
use function array_keys;
use function count;
use function iterator_to_array;

class EventEngineStateResourceNameCollectionFactory implements ResourceNameCollectionFactoryInterface
{
    /** @param string[] $paths */
    public function __construct(
        private readonly array $paths,
        private readonly ResourceNameCollectionFactoryInterface|null $decorated = null,
    ) {
    }

    public function create(): ResourceNameCollection
    {
        $classes = [];

        if ($this->decorated) {
            $resourceClasses = iterator_to_array($this->decorated->create());
            $classes = array_combine($resourceClasses, array_fill(0, count($resourceClasses), true));
        }

        /** @var array<ReflectionClass<object>> $reflectionClasses */
        $reflectionClasses = ReflectionClassRecursiveIterator::getReflectionClassesFromDirectories($this->paths);
        foreach ($reflectionClasses as $className => $reflectionClass) {
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
        return ! empty($reflectionClass->getAttributes(EventEngineState::class));
    }
}
