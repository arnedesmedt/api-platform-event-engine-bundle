<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\FinderException;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Finder;
use ADS\Bundle\ApiPlatformEventEngineBundle\Util\JsonSchema;
use ADS\Bundle\EventEngineBundle\Message\HasResponses;
use ApiPlatform\Core\Api\OperationMethodResolverInterface;
use ApiPlatform\Core\JsonSchema\Schema;
use ApiPlatform\Core\JsonSchema\SchemaFactoryInterface;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use function sprintf;
use function ucfirst;

final class MessageSchemaFactory implements SchemaFactoryInterface
{
    private SchemaFactoryInterface $schemaFactory;
    private Finder $messageFinder;
    private OperationMethodResolverInterface $operationMethodResolver;

    public function __construct(
        SchemaFactoryInterface $schemaFactory,
        Finder $messageFinder,
        OperationMethodResolverInterface $operationMethodResolver
    ) {
        $this->schemaFactory = $schemaFactory;
        $this->messageFinder = $messageFinder;
        $this->operationMethodResolver = $operationMethodResolver;
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

        if ($type === Schema::TYPE_INPUT) {
            return JsonSchema::toApiPlatformSchema($message::__schema()->toArray(), $schema);
        }

        $reflectionClass = new ReflectionClass($message);

        if (! $reflectionClass->implementsInterface(HasResponses::class)) {
            return new Schema();
        }

        return JsonSchema::toApiPlatformSchema(
            $message::__responseSchemaForStatusCode(
                $message::__defaultStatusCode() ?? $this->defaultStatusCode($className, $operationType, $operationName)
            )
                ->toArray(),
            $schema
        );
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
        $method = sprintf('get%sOperationMethod', ucfirst($operationType));

        $httpMethod = $this->operationMethodResolver->{$method}($className, $operationName);

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
