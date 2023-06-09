<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\TypeFactory\MessageTypeFactory;
use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\OpenApi\Factory\OpenApiFactory;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

use function implode;
use function in_array;
use function preg_replace;
use function sprintf;

final class MessageRefSchemaFactory implements SchemaFactoryInterface
{
    /**
     * @param class-string<JsonSchemaAwareRecord> $className
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
        $schema = $schema ? clone $schema : new Schema(Schema::VERSION_OPENAPI);

        if (MessageTypeFactory::isComplexType($className)) {
            if ($forceCollection) {
                $schema['type'] = 'array';
                $schema['items'] = ['type' => MessageTypeFactory::complexType($className)];

                return $schema;
            }

            $schema['type'] = MessageTypeFactory::complexType($className);

            return $schema;
        }

        $version = $schema->getVersion();
        $method = $operation instanceof HttpOperation ? $operation->getMethod() : 'GET';

        if ($operation) {
            $serializerContext ??= $type === Schema::TYPE_OUTPUT
                ? $operation->getNormalizationContext()
                : $operation->getDenormalizationContext();
        }

        if (! in_array($method, [Request::METHOD_GET, Request::METHOD_OPTIONS]) && $type === Schema::TYPE_OUTPUT) {
            $serializerContext[AbstractNormalizer::GROUPS] = [];
        }

        $definitionName = self::buildDefinitionName($className, $format, $type, $operation, $serializerContext);

        if (! isset($schema['$ref']) && ! isset($schema['type'])) {
            $ref = $version === Schema::VERSION_OPENAPI
                ? '#/components/schemas/' . $definitionName
                : '#/definitions/' . $definitionName;
            if ($forceCollection || ($method !== 'POST' && $operation instanceof CollectionOperationInterface)) {
                $schema['type'] = 'array';
                $schema['items'] = ['$ref' => $ref];
            } else {
                $schema['$ref'] = $ref;
            }
        }

        return $schema;
    }

    /**
     * @param class-string<JsonSchemaAwareRecord> $className
     * @param array<string, string>|null $serializerContext
     */
    public static function buildDefinitionName(
        string $className,
        string $format = 'json',
        string $type = Schema::TYPE_OUTPUT,
        Operation|null $operation = null,
        array|null $serializerContext = null,
    ): string {
        $inputOrOutput = ['class' => $className];

        if ($operation) {
            $inputOrOutput = $type === Schema::TYPE_OUTPUT
                ? ($operation->getOutput() ?? $inputOrOutput)
                : ($operation->getInput() ?? $inputOrOutput);

            $prefix = $operation->getShortName();
        }

        if (! isset($prefix)) {
            $prefix = self::nameFromClass($className);
        }

        if ($inputOrOutput['class'] !== null && $className !== $inputOrOutput['class']) {
            $prefix .= '.' . self::nameFromClass($inputOrOutput['class']);
        }

        if ($format !== 'json') {
            // JSON is the default, and so isn't included in the definition name
            $prefix .= '.' . $format;
        }

        $definitionName = $serializerContext[OpenApiFactory::OPENAPI_DEFINITION_NAME] ?? null;
        if ($definitionName) {
            $name = sprintf('%s-%s', $prefix, $definitionName);
        } else {
            $groups = (array) ($serializerContext[AbstractNormalizer::GROUPS] ?? []);
            $name = $groups ? sprintf('%s-%s', $prefix, implode('_', $groups)) : $prefix;
        }

        return self::encodeDefinitionName($name);
    }

    private static function encodeDefinitionName(string $name): string
    {
        $encodedDefinitionName = preg_replace('/[^a-zA-Z0-9.\-_]/', '.', $name);

        if ($encodedDefinitionName === null) {
            throw new RuntimeException(sprintf('Could not encode definition name for \'%s\'.', $name));
        }

        return $encodedDefinitionName;
    }

    /** @param class-string<JsonSchemaAwareRecord> $className */
    private static function nameFromClass(string $className): string
    {
        $reflectionClass = new ReflectionClass($className);

        return $reflectionClass->implementsInterface(JsonSchemaAwareRecord::class)
            ? $className::__type()
            : $reflectionClass->getShortName();
    }
}
