<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyMetadataFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\JsonImmutableObjects\HasPropertyExamples;
use ADS\Util\StringUtil;
use ADS\ValueObjects\ValueObject;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Property\PropertyMetadata;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use InvalidArgumentException;
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
        $reflectionClass = new ReflectionClass($resourceClass);

        if (! $reflectionClass->implementsInterface(JsonSchemaAwareRecord::class)) {
            return $propertyMetadata;
        }

        $schema = $resourceClass::__schema()->toArray();
        $propertySchema = $schema['properties'][$property] ?? [];

        $propertyDefault = null;
        try {
            if (
                method_exists($resourceClass, 'propertyDefault')
                && method_exists($resourceClass, 'defaultProperties')
            ) {
                $propertyDefault = $resourceClass::propertyDefault($property, $resourceClass::defaultProperties());
            }
        } catch (RuntimeException $exception) {
        }

        if ($reflectionClass->hasProperty($property)) {
            $reflectionProperty = $reflectionClass->getProperty($property);
            /** @var ReflectionNamedType|null $propertyType */
            $propertyType = $reflectionProperty->getType();
        }

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
        } elseif (isset($propertyType) && ! $propertyType->isBuiltin()) {
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

        if ($reflectionClass->implementsInterface(HasPropertyExamples::class)) {
            $examples = $resourceClass::examples();
            $example = $examples[$property] ?? null;

            if ($example) {
                if ($example instanceof ValueObject) {
                    $example = $example->toValue();
                }

                $propertyMetadata = $propertyMetadata->withExample($example);
            }
        }

        return $propertyMetadata
            ->withRequired(in_array($property, $schema['required'] ?? []))
            ->withDefault($propertyDefault)
            ->withReadable(true)
            ->withWritable(true)
            ->withReadableLink(true);
    }
}
