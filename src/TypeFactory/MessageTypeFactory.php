<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\TypeFactory;

use ADS\ValueObjects\HasExamples;
use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\TypeFactoryInterface;
use EventEngine\JsonSchema\ProvidesValidationRules;
use ReflectionClass;
use Symfony\Component\PropertyInfo\Type;

use function addslashes;
use function preg_match;
use function preg_quote;
use function reset;
use function sprintf;

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
        ?bool $readableLink = null,
        ?array $serializerContext = null,
        ?Schema $schema = null
    ): array {
        $newType = $this->typeFactory->getType($type, $format, $readableLink, $serializerContext, $schema);

        if ($type->isCollection()) {
            $keyType = $type->getCollectionKeyTypes();
            $firstKeyType = reset($keyType);

            $key = $firstKeyType !== false && $firstKeyType->getBuiltinType() === Type::BUILTIN_TYPE_STRING
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

        if (self::complexType($className)) {
            if (isset($existingType['$ref'])) {
                unset($existingType['$ref']);
            }

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

    public static function complexType(string $className): bool
    {
        return isset($_GET['complex'])
            && preg_match(sprintf('#%s#', preg_quote($_GET['complex'], '#')), $className);
    }
}
