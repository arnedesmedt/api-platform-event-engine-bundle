<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Loader;

use Doctrine\Common\Annotations\Reader;
use Error;
use EventEngine\Data\ImmutableRecord;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Exception\MappingException;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\AttributeMetadataInterface;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;

use function is_a;
use function sprintf;

class ImmutableRecordLoader implements LoaderInterface
{
    private const KNOWN_ANNOTATIONS = [
        DiscriminatorMap::class,
        Groups::class,
        Ignore::class,
        MaxDepth::class,
        SerializedName::class,
        Context::class,
    ];

    public function __construct(
        private readonly Reader|null $reader,
        private readonly LoaderInterface|null $loader,
    ) {
    }

    public function loadClassMetadata(ClassMetadataInterface $classMetadata): bool
    {
        $reflectionClass = $classMetadata->getReflectionClass();
        $className = $reflectionClass->name;

        // Quick fix to ignore the properties from this user interface.
        // todo find a better way to ignore the properties from the user interface.
        if ($className === UserInterface::class) {
            return true;
        }

        $attributesMetadata = $classMetadata->getAttributesMetadata();

        if (! $reflectionClass->implementsInterface(ImmutableRecord::class) && $this->loader) {
            return $this->loader->loadClassMetadata($classMetadata);
        }

        if ($reflectionClass->isInterface()) {
            return true;
        }

        foreach ($reflectionClass->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            if (! isset($attributesMetadata[$property->name])) {
                $attributesMetadata[$property->name] = new AttributeMetadata($property->name);
                $classMetadata->addAttributeMetadata($attributesMetadata[$property->name]);
            }

            if ($property->getDeclaringClass()->name !== $className) {
                continue;
            }

            foreach ($this->loadAnnotations($property) as $annotation) {
                if ($annotation instanceof Groups) {
                    foreach ($annotation->getGroups() as $group) {
                        $attributesMetadata[$property->name]->addGroup($group);
                    }
                } elseif ($annotation instanceof MaxDepth) {
                    $attributesMetadata[$property->name]->setMaxDepth($annotation->getMaxDepth());
                } elseif ($annotation instanceof SerializedName) {
                    $attributesMetadata[$property->name]->setSerializedName($annotation->getSerializedName());
                } elseif ($annotation instanceof Ignore) {
                    $attributesMetadata[$property->name]->setIgnore(true);
                } elseif ($annotation instanceof Context) {
                    $this->setAttributeContextsForGroups($annotation, $attributesMetadata[$property->name]);
                }
            }
        }

        return true;
    }

    /**
     * @param ReflectionClass<object>|ReflectionMethod|ReflectionProperty $reflector
     *
     * @return iterable<mixed>
     */
    public function loadAnnotations(object $reflector): iterable
    {
        foreach ($reflector->getAttributes() as $attribute) {
            if (! $this->isKnownAttribute($attribute->getName())) {
                continue;
            }

            try {
                yield $attribute->newInstance();
            } catch (Error $e) {
                if ($e::class !== Error::class) {
                    throw $e;
                }

                $on = match (true) {
                    $reflector instanceof ReflectionClass => ' on class ' . $reflector->name,
                    $reflector instanceof ReflectionMethod => sprintf(
                        ' on "%s::%s()"',
                        $reflector->getDeclaringClass()->name,
                        $reflector->name
                    ),
                    $reflector instanceof ReflectionProperty => sprintf(
                        ' on "%s::$%s"',
                        $reflector->getDeclaringClass()->name,
                        $reflector->name
                    ),
                };

                throw new MappingException(
                    sprintf('Could not instantiate attribute "%s"%s.', $attribute->getName(), $on),
                    0,
                    $e
                );
            }
        }

        if ($this->reader === null) {
            return;
        }

        if ($reflector instanceof ReflectionClass) {
            yield from $this->reader->getClassAnnotations($reflector);
        }

        if ($reflector instanceof ReflectionMethod) {
            yield from $this->reader->getMethodAnnotations($reflector);
        }

        if (! ($reflector instanceof ReflectionProperty)) {
            return;
        }

        yield from $this->reader->getPropertyAnnotations($reflector);
    }

    private function setAttributeContextsForGroups(
        Context $annotation,
        AttributeMetadataInterface $attributeMetadata
    ): void {
        if ($annotation->getContext()) {
            $attributeMetadata->setNormalizationContextForGroups(
                $annotation->getContext(),
                $annotation->getGroups()
            );
            $attributeMetadata->setDenormalizationContextForGroups(
                $annotation->getContext(),
                $annotation->getGroups()
            );
        }

        if ($annotation->getNormalizationContext()) {
            $attributeMetadata->setNormalizationContextForGroups(
                $annotation->getNormalizationContext(),
                $annotation->getGroups()
            );
        }

        if (! $annotation->getDenormalizationContext()) {
            return;
        }

        $attributeMetadata->setDenormalizationContextForGroups(
            $annotation->getDenormalizationContext(),
            $annotation->getGroups()
        );
    }

    private function isKnownAttribute(string $attributeName): bool
    {
        foreach (self::KNOWN_ANNOTATIONS as $knownAnnotation) {
            if (is_a($attributeName, $knownAnnotation, true)) {
                return true;
            }
        }

        return false;
    }
}
