<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\TypeFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Documentation\ComplexTypeExtractor;
use ADS\ValueObjects\HasExamples;
use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\TypeFactoryInterface;
use EventEngine\JsonSchema\ProvidesValidationRules;
use ReflectionClass;
use Symfony\Component\PropertyInfo\Type;

use function reset;

final class MessageTypeFactory implements TypeFactoryInterface
{
    public function __construct(private TypeFactoryInterface $typeFactory)
    {
    }

    /**
     * @param Schema<mixed>|null $schema
     * @param array<mixed>|null $serializerContext
     *
     * @return mixed[]
     */
    public function getType(
        Type $type,
        string $format = 'json',
        bool|null $readableLink = null,
        array|null $serializerContext = null,
        Schema|null $schema = null,
    ): array {
        if (ComplexTypeExtractor::isClassComplexType($type->getClassName())) {
            return [];
        }

        $newType = $this->typeFactory->getType($type, $format, $readableLink, $serializerContext, $schema);

        if ($type->isCollection()) {
            $keyType = $type->getCollectionKeyTypes();
            $valueType = $type->getCollectionValueTypes();
            $firstKeyType = reset($keyType);
            $firstValueType = reset($valueType);

            if (! $firstValueType || empty($newType)) {
                return $newType;
            }

            $key = $firstKeyType !== false && $firstKeyType->getBuiltinType() === Type::BUILTIN_TYPE_STRING
                ? 'additionalProperties'
                : 'items';

            $newType[$key] = $this->extraMessageTypeConversion($newType[$key], $firstValueType);
        } else {
            $newType = $this->extraMessageTypeConversion($newType, $type);
        }

        return $newType;
    }

    /**
     * @param array<mixed> $existingType
     *
     * @return array<mixed>
     */
    private function extraMessageTypeConversion(array $existingType, Type $type): array
    {
        /** @var class-string|null $className */
        $className = $type->getClassName();
        if ($className === null) {
            return $existingType;
        }

        $reflectionClass = new ReflectionClass($className);

        if (ComplexTypeExtractor::isClassComplexType($className)) {
            if (isset($existingType['$ref'])) {
                unset($existingType['$ref']);
            }

            $existingType['type'] = ComplexTypeExtractor::isClassComplexType($className);
        }

        if ($reflectionClass->implementsInterface(ProvidesValidationRules::class)) {
            $existingType += $className::validationRules();
        }

        if ($reflectionClass->implementsInterface(HasExamples::class)) {
            $example = $className::example();
            $existingType += ['example' => $example->toValue()];
        }

        return $existingType;
    }
}
