<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceNameCollectionFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Attribute\EventEngineState;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use ApiPlatform\Metadata\Util\ReflectionClassRecursiveIterator;
use ReflectionClass;

use function array_keys;

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
            foreach ($this->decorated->create() as $resourceClass) {
                $classes[$resourceClass] = true;
            }
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
