<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\FinderException;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Finder;
use ADS\Bundle\ApiPlatformEventEngineBundle\Util\JsonSchema;
use ADS\Bundle\EventEngineBundle\Message\HasResponses;
use ApiPlatform\Core\JsonSchema\Schema;
use ApiPlatform\Core\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use EventEngine\EventEngine;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use function is_string;
use function sprintf;

final class MessageSchemaFactory implements SchemaFactoryInterface
{
    private SchemaFactoryInterface $schemaFactory;
    private Finder $messageFinder;
    private ResourceMetadataFactoryInterface $resourceMetaDataFactory;
    private EventEngine $eventEngine;

    public function __construct(
        SchemaFactoryInterface $schemaFactory,
        Finder $messageFinder,
        ResourceMetadataFactoryInterface $resourceMetaDataFactory,
        EventEngine $eventEngine
    ) {
        $this->schemaFactory = $schemaFactory;
        $this->messageFinder = $messageFinder;
        $this->resourceMetaDataFactory = $resourceMetaDataFactory;
        $this->eventEngine = $eventEngine;
    }

    /**
     * @param array<mixed>|null $serializerContext
     * @param Schema<mixed> $schema
     *
     * @return Schema<mixed>
     */
    public function buildSchema(
        string $className,
        string $format = 'json',
        string $type = Schema::TYPE_OUTPUT,
        ?string $operationType = null,
        ?string $operationName = null,
        ?Schema $schema = null,
        ?array $serializerContext = null,
        bool $forceCollection = false
    ) : Schema {
        $message = $this->message($className, $operationType, $operationName);

        if (! $message || $operationType === null || $operationName === null) {
            return $this->schemaFactory->buildSchema(
                $className,
                $format,
                $type,
                $operationType,
                $operationName,
                $schema,
                $serializerContext,
                $forceCollection
            );
        }

        $schema ??= new Schema();
        $reflectionClass = new ReflectionClass($message);

        if ($type === Schema::TYPE_OUTPUT && ! $reflectionClass->implementsInterface(HasResponses::class)) {
            return $schema;
        }

        if ($type === Schema::TYPE_INPUT) {
            JsonSchema::toApiPlatformSchema($message::__schema()->toArray(), $schema);
        } else {
            JsonSchema::toApiPlatformSchema(
                $message::__responseSchemaForStatusCode(
                    $message::__defaultStatusCode() ?? $this->defaultStatusCode($className, $operationType, $operationName)
                )
                    ->toArray(),
                $schema
            );
        }

        $definitions = $schema->getDefinitions();

        if ($definitions->count() === 0) {
            return $schema;
        }

        $responseTypes = $this->eventEngine->compileCacheableConfig()['responseTypes'];

        $iterator = $definitions->getIterator();

        while ($iterator->valid()) {
            $definitionName = $iterator->current();

            if (! is_string($definitionName) || ! $this->eventEngine->isKnownType($definitionName)) {
                $iterator->next();
                continue;
            }

            $definitions[$definitionName] = $responseTypes[$definitionName];
            $iterator->next();
        }

        return $schema;
    }

    /**
     * @return class-string|null
     */
    private function message(string $className, ?string $operationType, ?string $operationName) : ?string
    {
        try {
            /** @var class-string $message */
            $message = $this->messageFinder->byContext(
                [
                    'resource_class' => $className,
                    'operation_type' => $operationType,
                    sprintf('%s_operation_name', $operationType) => $operationName,
                ]
            );
        } catch (FinderException $exception) {
            return null;
        }

        $reflectionClass = new ReflectionClass($message);

        return $reflectionClass->implementsInterface(JsonSchemaAwareRecord::class) ? $message : null;
    }

    private function defaultStatusCode(string $className, string $operationType, string $operationName) : int
    {
        $resourceMetaData = $this->resourceMetaDataFactory->create($className);
        $httpMethod = $resourceMetaData->getTypedOperationAttribute($operationType, $operationName, 'method');

        switch ($httpMethod) {
            case Request::METHOD_POST:
                return Response::HTTP_CREATED;
            case Request::METHOD_DELETE:
                return Response::HTTP_NO_CONTENT;
            default:
                return Response::HTTP_OK;
        }
    }
}
