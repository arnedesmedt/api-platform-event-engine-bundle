<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyMetadataFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\TypeFactory\MessageTypeFactory;
use ADS\JsonImmutableObjects\HasPropertyExamples;
use ADS\Util\StringUtil;
use ADS\ValueObjects\ValueObject;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use EventEngine\Data\ImmutableRecord;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use InvalidArgumentException;
use JetBrains\PhpStorm\Deprecated;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

use function array_filter;
use function in_array;
use function method_exists;
use function sprintf;

final class JsonSchemaPropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    /** @readonly */
    private DocBlockFactory $docBlockFactory;

    public function __construct(
        private PropertyMetadataFactoryInterface $decorated,
    ) {
        $this->docBlockFactory = DocBlockFactory::createInstance();
    }

    /**
     * @param class-string $resourceClass
     * @param array<mixed> $options
     */
    public function create(string $resourceClass, string $property, array $options = []): ApiProperty
    {
        // todo change the way we work with camelize and decamilize
        $property = StringUtil::camelize($property);
        $apiProperty = $this->decorated->create($resourceClass, $property, $options);
        /** @var ReflectionClass<ImmutableRecord> $reflectionClass */
        $reflectionClass = new ReflectionClass($resourceClass);

        if (! $reflectionClass->implementsInterface(JsonSchemaAwareRecord::class)) {
            return $apiProperty;
        }

        $schema = $resourceClass::__schema()->toArray();
        $propertySchema = $schema['properties'][$property] ?? [];

        $builtinTypes = $apiProperty->getBuiltinTypes();
        $firstBuiltInType = $builtinTypes[0] ?? null;
        $className = $firstBuiltInType?->getClassName();

        return $apiProperty
            ->withDefault($this->default($resourceClass, $property))
            ->withDescription(
                $this->description($apiProperty, $resourceClass, $property, $reflectionClass, $propertySchema) ?? '',
            )
            ->withExample($this->example($resourceClass, $property, $reflectionClass) ?? null)
            ->withDeprecationReason($this->deprecationReason($property, $reflectionClass))
            ->withRequired(in_array($property, $schema['required'] ?? []))
            ->withReadable(true)
            ->withWritable(true)
            ->withReadableLink(true)
            ->withOpenapiContext(array_filter(['type' => MessageTypeFactory::complexType($className)]));
    }

    private function default(string $resourceClass, string $property): mixed
    {
        try {
            if (
                method_exists($resourceClass, 'propertyDefault')
                && method_exists($resourceClass, 'defaultProperties')
            ) {
                return $resourceClass::propertyDefault($resourceClass::defaultProperties(), $property);
            }
        } catch (RuntimeException) {
        }

        return null;
    }

    /**
     * @param ReflectionClass<ImmutableRecord> $reflectionClass
     * @param array<mixed>                     $propertySchema
     */
    private function description(
        ApiProperty $apiProperty,
        string $resourceClass,
        string $property,
        ReflectionClass $reflectionClass,
        array $propertySchema,
    ): string|null {
        /** @var ReflectionNamedType|null $propertyType */
        $propertyType = $reflectionClass->hasProperty($property)
            ? $reflectionClass->getProperty($property)->getType()
            : null;

        $patchPropertyDescription = $reflectionClass->implementsInterface(ApiPlatformMessage::class)
        && $resourceClass::__httpMethod() === Request::METHOD_PATCH
        && isset($propertyType)
        && $propertyType->allowsNull()
            ? sprintf(
                "\n If '%s' is not added in the payload, then it will not be used.",
                StringUtil::decamelize($property),
            )
            : '';

        if ($propertySchema['description'] ?? false) {
            return $propertySchema['description'] . $patchPropertyDescription;
        }

        if (isset($propertyType) && ! $propertyType->isBuiltin()) {
            // Get the description of the value object
            /** @var class-string $className */
            $className = $propertyType->getName();
            $propertyReflectionClass = new ReflectionClass($className);

            try {
                $docBlock = $this->docBlockFactory->create($propertyReflectionClass);
                $description = $docBlock->getDescription()->render();

                return sprintf(
                    '%s%s%s',
                    $docBlock->getSummary(),
                    ! empty($description) ? "\n" . $description : $description,
                    $patchPropertyDescription,
                );
            } catch (InvalidArgumentException) {
            }

            return null;
        }

        if ($patchPropertyDescription) {
            return $apiProperty->getDescription() ?? '' . $patchPropertyDescription;
        }

        return null;
    }

    /** @param ReflectionClass<ImmutableRecord> $reflectionClass */
    private function example(
        string $resourceClass,
        string $property,
        ReflectionClass $reflectionClass,
    ): mixed {
        if ($reflectionClass->implementsInterface(HasPropertyExamples::class)) {
            $examples = $resourceClass::examples();
            $example = $examples[$property] ?? null;

            if ($example) {
                if ($example instanceof ValueObject) {
                    $example = $example->toValue();
                }

                return $example;
            }
        }

        $docTags = $this->docTagsFromProperty($reflectionClass, $property, 'example');

        if (empty($docTags)) {
            return null;
        }

        /** @var DocBlock\Tags\Example $exampleTag */
        $exampleTag = $docTags[0];

        return (string) $exampleTag;
    }

    /** @param ReflectionClass<ImmutableRecord> $reflectionClass */
    private function deprecationReason(
        string $property,
        ReflectionClass $reflectionClass,
    ): string|null {
        $tags = $this->docTagsFromProperty($reflectionClass, $property, 'deprecated');

        if (! empty($tags)) {
            /** @var DocBlock\Tags\Deprecated $deprecatedTag */
            $deprecatedTag = $tags[0];
            $reason = (string) $deprecatedTag;

            return empty($reason) ? 'deprecated' : $reason;
        }

        $attributes = $this->attributesFromProperty($reflectionClass, $property, Deprecated::class);

        if (! empty($attributes)) {
            /** @var ReflectionAttribute<Deprecated> $deprecatedAttribute */
            $deprecatedAttribute = $attributes[0];
            $reason = $deprecatedAttribute->getArguments()[0] ?? '';

            return empty($reason) ? 'deprecated' : $reason;
        }

        return null;
    }

    /**
     * @param ReflectionClass<ImmutableRecord> $reflectionClass
     *
     * @return array<DocBlock\Tag>
     */
    private function docTagsFromProperty(
        ReflectionClass $reflectionClass,
        string $property,
        string $tagName,
    ): array {
        $tags = [];
        if (! $reflectionClass->hasProperty($property)) {
            return $tags;
        }

        $reflectionProperty = $reflectionClass->getProperty($property);

        try {
            $docBlock = $this->docBlockFactory->create($reflectionProperty);
            $tags = $docBlock->getTagsByName($tagName);
        } catch (InvalidArgumentException) {
        }

        return $tags;
    }

    /**
     * @param ReflectionClass<ImmutableRecord> $reflectionClass
     *
     * @return array<ReflectionAttribute<object>>
     */
    private function attributesFromProperty(
        ReflectionClass $reflectionClass,
        string $property,
        string $attributeName,
    ): array {
        $attributes = [];
        if (! $reflectionClass->hasProperty($property)) {
            return $attributes;
        }

        $reflectionProperty = $reflectionClass->getProperty($property);

        return $reflectionProperty->getAttributes($attributeName);
    }
}
