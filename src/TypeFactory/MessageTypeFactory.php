<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\TypeFactory;

use ADS\ValueObjects\HasExamples;
use ADS\ValueObjects\ValueObject;
use ApiPlatform\Core\JsonSchema\Schema;
use ApiPlatform\Core\JsonSchema\TypeFactoryInterface;
use EventEngine\JsonSchema\ProvidesValidationRules;
use ReflectionClass;
use Symfony\Component\PropertyInfo\Type;

use function addslashes;
use function preg_match;
use function preg_quote;
use function sprintf;

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

        if ($type->isCollection()) {
            $keyType = $type->getCollectionKeyType();

            $key = $keyType !== null && $keyType->getBuiltinType() === Type::BUILTIN_TYPE_STRING
                ? 'additionalProperties'
                : 'items';

            $newType[$key] = $this->extraMessageTypeConversion($newType[$key], $type);
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

        if (
            isset($_GET['complex'])
            && preg_match(sprintf('#%s#', preg_quote($_GET['complex'], '#')), $className)
            && $reflectionClass->implementsInterface(ValueObject::class)
        ) {
            $existingType['type'] = '\\' . addslashes($className);
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
