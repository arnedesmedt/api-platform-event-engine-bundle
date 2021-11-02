<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyMetadataFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\JsonImmutableObjects\HasPropertyExamples;
use ADS\Util\StringUtil;
use ADS\ValueObjects\ValueObject;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Property\PropertyMetadata;
use EventEngine\Data\ImmutableRecord;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use InvalidArgumentException;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

use function in_array;
use function method_exists;
use function sprintf;

final class JsonSchemaPropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    private PropertyMetadataFactoryInterface $decorated;
    private DocBlockFactory $docBlockFactory;

    public function __construct(
        PropertyMetadataFactoryInterface $decorated
    ) {
        $this->decorated = $decorated;
        $this->docBlockFactory = DocBlockFactory::createInstance();
    }

    /**
     * @param class-string $resourceClass
     * @param array<mixed> $options
     */
    public function create(string $resourceClass, string $property, array $options = []): PropertyMetadata
    {
        $propertyMetadata = $this->decorated->create($resourceClass, $property, $options);
        /** @var ReflectionClass<ImmutableRecord> $reflectionClass */
        $reflectionClass = new ReflectionClass($resourceClass);

        if (! $reflectionClass->implementsInterface(JsonSchemaAwareRecord::class)) {
            return $propertyMetadata;
        }

        $schema = $resourceClass::__schema()->toArray();
        $propertySchema = $schema['properties'][$property] ?? [];

        $this
            ->addDefault($propertyMetadata, $resourceClass, $property)
            ->addDescription($propertyMetadata, $resourceClass, $property, $reflectionClass, $propertySchema)
            ->addExample($propertyMetadata, $resourceClass, $property, $reflectionClass)
            ->addDeprecated($propertyMetadata, $property, $reflectionClass);

        return $propertyMetadata
            ->withRequired(in_array($property, $schema['required'] ?? []))
            ->withReadable(true)
            ->withWritable(true)
            ->withReadableLink(true);
    }

    private function addDefault(PropertyMetadata &$propertyMetadata, string $resourceClass, string $property): self
    {
        try {
            if (
                method_exists($resourceClass, 'propertyDefault')
                && method_exists($resourceClass, 'defaultProperties')
            ) {
                $default = $resourceClass::propertyDefault($property, $resourceClass::defaultProperties());
            }
        } catch (RuntimeException $exception) {
        }

        $propertyMetadata = $propertyMetadata->withDefault($default ?? null);

        return $this;
    }

    /**
     * @param ReflectionClass<ImmutableRecord> $reflectionClass
     * @param array<mixed> $propertySchema
     */
    private function addDescription(
        PropertyMetadata &$propertyMetadata,
        string $resourceClass,
        string $property,
        ReflectionClass $reflectionClass,
        array $propertySchema
    ): self {
        /** @var ReflectionNamedType|null $propertyType */
        $propertyType = $reflectionClass->hasProperty($property)
            ? $reflectionClass->getProperty($property)->getType()
            : null;

        $patchPropertyDescription = $reflectionClass->implementsInterface(ApiPlatformMessage::class)
        && $resourceClass::__httpMethod() === Request::METHOD_PATCH
        && isset($propertyType)
        && $propertyType->allowsNull()
            ? sprintf(
                '<br/> If \'%s\' is not added in the payload, then it will not be used.',
                StringUtil::decamelize($property)
            )
            : '';

        if ($propertySchema['description'] ?? false) {
            $propertyMetadata = $propertyMetadata->withDescription(
                $propertySchema['description'] . $patchPropertyDescription
            );

            return $this;
        }

        if (isset($propertyType) && ! $propertyType->isBuiltin()) {
            // Get the description of the value object
            /** @var class-string $className */
            $className = $propertyType->getName();
            $propertyReflectionClass = new ReflectionClass($className);

            try {
                $docBlock = $this->docBlockFactory->create($propertyReflectionClass);
                $propertyMetadata = $propertyMetadata->withDescription(
                    sprintf(
                        '%s<br/>%s%s',
                        $docBlock->getSummary(),
                        $docBlock->getDescription()->render(),
                        $patchPropertyDescription
                    )
                );
            } catch (InvalidArgumentException $exception) {
            }
        }

        return $this;
    }

    /**
     * @param ReflectionClass<ImmutableRecord> $reflectionClass
     */
    private function addExample(
        PropertyMetadata &$propertyMetadata,
        string $resourceClass,
        string $property,
        ReflectionClass $reflectionClass
    ): self {
        if ($reflectionClass->implementsInterface(HasPropertyExamples::class)) {
            $examples = $resourceClass::examples();
            $example = $examples[$property] ?? null;

            if ($example) {
                if ($example instanceof ValueObject) {
                    $example = $example->toValue();
                }

                $propertyMetadata = $propertyMetadata->withExample($example);

                return $this;
            }
        }

        $tags = $this->docTagsFromProperty($reflectionClass, $property, 'example');

        if (empty($tags)) {
            return $this;
        }

        /** @var DocBlock\Tags\Example $exampleTag */
        $exampleTag = $tags[0];
        $description = $exampleTag->getDescription();

        $propertyMetadata = $propertyMetadata->withExample($description);

        return $this;
    }

    /**
     * @param ReflectionClass<ImmutableRecord> $reflectionClass
     */
    private function addDeprecated(
        PropertyMetadata &$propertyMetadata,
        string $property,
        ReflectionClass $reflectionClass
    ): self {
        $tags = $this->docTagsFromProperty($reflectionClass, $property, 'deprecated');

        if (empty($tags)) {
            return $this;
        }

        /** @var DocBlock\Tags\Deprecated $deprecatedTag */
        $deprecatedTag = $tags[0];
        $description = $deprecatedTag->getDescription();

        $propertyMetadata = $propertyMetadata->withAttributes(
            [
                'deprcation_reason' => $description ? $description->render() : 'deprecated',
            ]
        );

        return $this;
    }

    /**
     * @param ReflectionClass<ImmutableRecord> $reflectionClass
     *
     * @return array<DocBlock\Tag>
     */
    private function docTagsFromProperty(ReflectionClass $reflectionClass, string $property, string $tagName): array
    {
        $reflectionProperty = $reflectionClass->getProperty($property);

        try {
            $docBlock = $this->docBlockFactory->create($reflectionProperty);
            $tags = $docBlock->getTagsByName($tagName);
        } catch (InvalidArgumentException $exception) {
            return [];
        }

        return $tags;
    }
}
