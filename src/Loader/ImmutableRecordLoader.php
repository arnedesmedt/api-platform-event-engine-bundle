<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Loader;

use EventEngine\Data\ImmutableRecord;
use ReflectionProperty;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;

use function array_filter;
use function array_flip;
use function array_intersect_key;
use function array_map;
use function assert;

class ImmutableRecordLoader implements LoaderInterface
{
    public function loadClassMetadata(ClassMetadataInterface $classMetadata): bool
    {
        assert($classMetadata instanceof ClassMetadata);

        $reflectionClass = $classMetadata->getReflectionClass();
        $className = $reflectionClass->name;

        if (! $reflectionClass->implementsInterface(ImmutableRecord::class)) {
            return false;
        }

        if ($reflectionClass->isInterface()) {
            return false;
        }

        $attributesMetadata = $classMetadata->getAttributesMetadata();

        $reflectionPropertiesToFilter = array_filter(
            $reflectionClass->getProperties(),
            static fn (ReflectionProperty $property): bool => ! $property->isStatic()
                && $property->getDeclaringClass()->name === $className,
        );
        $propertyNamesToFilter = array_map(
            static fn (ReflectionProperty $property): string => $property->name,
            $reflectionPropertiesToFilter,
        );

        $attributesMetadata = array_intersect_key($attributesMetadata, array_flip($propertyNamesToFilter));

        $classMetadata->attributesMetadata = $attributesMetadata;

        return true;
    }
}
