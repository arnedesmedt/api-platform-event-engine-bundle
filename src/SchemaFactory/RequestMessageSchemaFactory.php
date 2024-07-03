<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\ValueObject\Uri;
use ADS\JsonImmutableObjects\Polymorphism\Discriminator;
use ADS\Util\ArrayUtil;
use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactory;
use ApiPlatform\JsonSchema\SchemaFactoryAwareInterface;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Util\ResourceClassInfoTrait;
use ArrayObject;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

use function array_flip;
use function array_values;
use function assert;
use function is_callable;
use function sprintf;

#[AsDecorator('api_platform.json_schema.schema_factory', priority: 2)]
final class RequestMessageSchemaFactory implements SchemaFactoryInterface, SchemaFactoryAwareInterface
{
    use ResourceClassInfoTrait;

    public function __construct(
        #[AutowireDecorated]
        private SchemaFactoryInterface $schemaFactory,
    ) {
        if (! ($this->schemaFactory instanceof SchemaFactoryAwareInterface)) {
            return;
        }

        $this->schemaFactory->setSchemaFactory($this);
    }

    /**
     * @param class-string<JsonSchemaAwareRecord|Discriminator> $className
     * @param array<string, mixed>|null $serializerContext
     * @param Schema<mixed>|null $schema
     *
     * @return Schema<mixed>
     */
    public function buildSchema(
        string $className,
        string $format = 'json',
        string $type = Schema::TYPE_OUTPUT,
        Operation|null $operation = null,
        Schema|null $schema = null,
        array|null $serializerContext = null,
        bool $forceCollection = false,
    ): Schema {
        $schema ??= new Schema(Schema::VERSION_OPENAPI);

        $input = $operation?->getInput();
        $messageClass = $input['class'] ?? null;
        $reflectionClass = $messageClass ? new ReflectionClass($messageClass) : null;

        if ($operation instanceof Delete) { // we need request bodies for delete operations
            // @see vendor/api-platform/core/src/JsonSchema/SchemaFactory#L87
            $serializerContext[SchemaFactory::FORCE_SUBSCHEMA] = true;
        }

        $schema = $this->schemaFactory->buildSchema(
            $className,
            $format,
            $type,
            $operation,
            $schema,
            $serializerContext,
            $forceCollection,
        );

        if (
            ! $messageClass
            || $operation === null
            || ! $reflectionClass?->implementsInterface(JsonSchemaAwareRecord::class)
            || $reflectionClass->implementsInterface(Discriminator::class)
            || $type !== Schema::TYPE_INPUT
        ) {
            return $schema;
        }

        assert($operation instanceof HttpOperation);

        $inputDefinitions = $schema->getDefinitions();
        /** @var string $rootDefinitionKey */
        $rootDefinitionKey = $schema->getRootDefinitionKey() ?? $schema->getItemsDefinitionKey();
        /** @var ArrayObject<string, mixed> $definition */
        $definition = $inputDefinitions->offsetGet($rootDefinitionKey);
        $definition = $definition->getArrayCopy();
        /** @var string $uriTemplate */
        $uriTemplate = $operation->getUriTemplate();
        /** @var array<string> $parameterNames */
        $parameterNames = ArrayUtil::toSnakeCasedValues(Uri::fromString($uriTemplate)->toAllParameterNames());
        $definition = self::removeParameters(
            $definition,
            $parameterNames,
        ) ?? ['type' => 'object'];
        $inputDefinitions[$rootDefinitionKey] = new ArrayObject($definition);

        $definitions = $schema->getDefinitions();
        $schema->setDefinitions(new ArrayObject([...$definitions, ...$inputDefinitions]));

        return $schema;
    }

    /**
     * @param array<mixed> $schema
     * @param array<string> $parametersNames
     *
     * @return array<string, mixed>|null
     */
    public static function filterParameters(array $schema, array $parametersNames): array|null
    {
        return self::changeParameters($schema, $parametersNames, 'array_intersect');
    }

    /**
     * @param array<mixed> $schema
     * @param array<string> $parameterNames
     *
     * @return array<string, mixed>
     */
    public static function removeParameters(array $schema, array $parameterNames): array|null
    {
        return self::changeParameters($schema, $parameterNames);
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string> $parameterNames
     *
     * @return array<string, mixed>
     */
    private static function changeParameters(
        array $schema,
        array $parameterNames,
        string $filterMethod = 'array_diff',
    ): array|null {
        $keyFilterMethod = sprintf('%s_key', $filterMethod);

        if (! is_callable($keyFilterMethod) || ! is_callable($filterMethod)) {
            throw new RuntimeException(
                sprintf(
                    'Method \'%s\' or \'%s\' is not callable',
                    $keyFilterMethod,
                    $filterMethod,
                ),
            );
        }

        $schema['properties'] ??= [];
        $schema['required'] ??= [];

        $filteredSchema = $schema;
        $filteredSchema['properties'] = $keyFilterMethod($schema['properties'], array_flip($parameterNames));

        if (empty($filteredSchema['properties'])) {
            return null;
        }

        $filteredSchema['required'] = array_values($filterMethod($schema['required'], $parameterNames));

        if (empty($filteredSchema['required'])) {
            unset($filteredSchema['required']);
        }

        return $filteredSchema;
    }

    public function setSchemaFactory(SchemaFactoryInterface $schemaFactory): void
    {
        if (! ($this->schemaFactory instanceof SchemaFactoryAwareInterface)) {
            return;
        }

        $this->schemaFactory->setSchemaFactory($schemaFactory);
    }
}
