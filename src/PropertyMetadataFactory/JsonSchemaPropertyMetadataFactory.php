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
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;
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
        private PropertyMetadataFactoryInterface $decorated
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

        $this
            ->addDefault($apiProperty, $resourceClass, $property)
            ->addDescription($apiProperty, $resourceClass, $property, $reflectionClass, $propertySchema)
            ->addExample($apiProperty, $resourceClass, $property, $reflectionClass)
            ->addDeprecated($apiProperty, $property, $reflectionClass);

        $className = $apiProperty->getBuiltinTypes()[0]?->getClassName();

        return $apiProperty
            ->withRequired(in_array($property, $schema['required'] ?? []))
            ->withReadable(true)
            ->withWritable(true)
            ->withReadableLink(true)
            ->withOpenapiContext(array_filter(
                [
                    'type' => MessageTypeFactory::complexType($className)
                        ? $className
                        : null,
                ]
            ));
    }

    private function addDefault(ApiProperty &$apiProperty, string $resourceClass, string $property): self
    {
        try {
            if (
                method_exists($resourceClass, 'propertyDefault')
                && method_exists($resourceClass, 'defaultProperties')
            ) {
                $default = $resourceClass::propertyDefault($property, $resourceClass::defaultProperties());
            }
        } catch (RuntimeException) {
        }

        $apiProperty = $apiProperty->withDefault($default ?? null);

        return $this;
    }

    /**
     * @param ReflectionClass<ImmutableRecord> $reflectionClass
     * @param array<mixed> $propertySchema
     */
    private function addDescription(
        ApiProperty &$apiProperty,
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
                "\n If '%s' is not added in the payload, then it will not be used.",
                StringUtil::decamelize($property)
            )
            : '';

        if ($propertySchema['description'] ?? false) {
            $apiProperty = $apiProperty->withDescription(
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
                $description = $docBlock->getDescription()->render();
                $apiProperty = $apiProperty->withDescription(
                    sprintf(
                        '%s%s%s',
                        $docBlock->getSummary(),
                        ! empty($description) ? "\n" . $description : $description,
                        $patchPropertyDescription
                    )
                );
            } catch (InvalidArgumentException) {
            }

            return $this;
        }

        if ($patchPropertyDescription) {
            $apiProperty = $apiProperty->withDescription(
                $apiProperty->getDescription() ?? '' . $patchPropertyDescription
            );
        }

        return $this;
    }

    /**
     * @param ReflectionClass<ImmutableRecord> $reflectionClass
     */
    private function addExample(
        ApiProperty &$apiProperty,
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

                $apiProperty = $apiProperty->withExample($example);

                return $this;
            }
        }

        $tags = $this->docTagsFromProperty($reflectionClass, $property, 'example');

        if (empty($tags)) {
            return $this;
        }

        /** @var DocBlock\Tags\Example $exampleTag */
        $exampleTag = $tags[0];

        $apiProperty = $apiProperty->withExample((string) $exampleTag);

        return $this;
    }

    /**
     * @param ReflectionClass<ImmutableRecord> $reflectionClass
     */
    private function addDeprecated(
        ApiProperty &$apiProperty,
        string $property,
        ReflectionClass $reflectionClass
    ): self {
        $tags = $this->docTagsFromProperty($reflectionClass, $property, 'deprecated');

        if (empty($tags)) {
            return $this;
        }

        /** @var DocBlock\Tags\Deprecated $deprecatedTag */
        $deprecatedTag = $tags[0];
        $reason = (string) $deprecatedTag;

        $apiProperty = $apiProperty->withDeprecationReason(empty($reason) ? 'deprecated' : $reason);

        return $this;
    }

    /**
     * @param ReflectionClass<ImmutableRecord> $reflectionClass
     *
     * @return array<DocBlock\Tag>
     */
    private function docTagsFromProperty(ReflectionClass $reflectionClass, string $property, string $tagName): array
    {
        if (! $reflectionClass->hasProperty($property)) {
            return [];
        }

        $reflectionProperty = $reflectionClass->getProperty($property);

        try {
            $docBlock = $this->docBlockFactory->create($reflectionProperty);
            $tags = $docBlock->getTagsByName($tagName);
        } catch (InvalidArgumentException) {
            return [];
        }

        return $tags;
    }
}
