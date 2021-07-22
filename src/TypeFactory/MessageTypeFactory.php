<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\TypeFactory;

use ADS\ValueObjects\HasExamples;
use ApiPlatform\Core\JsonSchema\Schema;
use ApiPlatform\Core\JsonSchema\TypeFactoryInterface;
use EventEngine\JsonSchema\ProvidesValidationRules;
use ReflectionClass;
use Symfony\Component\PropertyInfo\Type;

final class MessageTypeFactory implements TypeFactoryInterface
{
    private TypeFactoryInterface $typeFactory;

    public function __construct(TypeFactoryInterface $typeFactory)
    {
        $this->typeFactory = $typeFactory;
    }

    /**
     * @param array<mixed>|null $serializerContext
     * @param Schema<mixed>|null $schema
     *
     * @return mixed[]
     */
    public function getType(
        Type $type,
        string $format = 'json',
        ?bool $readableLink = null,
        ?array $serializerContext = null,
        ?Schema $schema = null
    ): array {
        $newType = $this->typeFactory->getType($type, $format, $readableLink, $serializerContext, $schema);

        $extraType = $this->extraMessageTypeConversion($type);

        if ($type->isCollection()) {
            $keyType = $type->getCollectionKeyType();

            $key = $keyType !== null && $keyType->getBuiltinType() === Type::BUILTIN_TYPE_STRING
                ? 'additionalProperties'
                : 'items';

            $newType[$key] += $extraType;
        } else {
            $newType += $extraType;
        }

        return $newType;
    }

    /**
     * @return array<mixed>
     */
    private function extraMessageTypeConversion(Type $type): array
    {
        $typeArray = [];
        /** @var class-string|null $className */
        $className = $type->getClassName();
        if ($className === null) {
            return $typeArray;
        }

        $reflectionClass = new ReflectionClass($className);

        if ($reflectionClass->implementsInterface(ProvidesValidationRules::class)) {
            $typeArray += $className::validationRules();
        }

        if ($reflectionClass->implementsInterface(HasExamples::class)) {
            $example = $className::example();
            $typeArray += ['example' => $example->toValue()];
        }

        return $typeArray;
    }
}
