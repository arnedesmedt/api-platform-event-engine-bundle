<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Documentation;

use ADS\Bundle\EventEngineBundle\Response\HasResponses;
use ApiPlatform\Core\JsonSchema\Schema;
use ApiPlatform\Core\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\Core\OpenApi\Model\MediaType;
use ApiPlatform\Core\OpenApi\Model\Operation;
use ApiPlatform\Core\OpenApi\Model\Response;
use ApiPlatform\Core\OpenApi\Model\Server;
use ApiPlatform\Core\OpenApi\OpenApi;
use ArrayObject;
use EventEngine\Data\ImmutableRecord;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;

use function array_map;
use function ceil;
use function count;
use function floor;
use function similar_text;
use function sprintf;
use function strtolower;
use function ucfirst;
use function usort;

final class OpenApiFactory implements OpenApiFactoryInterface
{
    public const OPERATION_METHODS = [
        Request::METHOD_GET,
        Request::METHOD_POST,
        Request::METHOD_DELETE,
        Request::METHOD_PUT,
        Request::METHOD_PATCH,
        Request::METHOD_HEAD,
        Request::METHOD_OPTIONS,
        Request::METHOD_TRACE,
    ];

    /**
     * @var Server[]
     * @readonly
     */
    private array $servers;

    /**
     * @var array<mixed>
     * @readonly
     */
    private array $tags;

    /**
     * @param array<string> $formats
     * @param array<array<string, string>> $servers
     * @param array<array<string>> $tags
     */
    public function __construct(
        private OpenApiFactoryInterface $openApiFactory,
        private ResourceMetadataFactoryInterface $resourceMetadataFactory,
        private SchemaFactoryInterface $jsonSchemaFactory,
        private array $formats = [],
        array $servers = [],
        array $tags = []
    ) {
        if (isset($_SERVER['HTTP_HOST'])) {
            usort(
                $servers,
                static function (array $server1, array $server2) {
                    $percentage1 = $percentage2 = 0.0;
                    similar_text($server2['url'], $_SERVER['HTTP_HOST'], $percentage2);
                    similar_text($server1['url'], $_SERVER['HTTP_HOST'], $percentage1);

                    $diff = ($percentage2 - $percentage1) / 100;

                    return (int) ($diff > 0 ? ceil($diff) : floor($diff));
                }
            );
        }

        $this->servers = array_map(
            static fn (array $server) => new Server($server['url'], $server['description']),
            $servers
        );

        $this->tags = array_map(
            static fn (string $tag) => ['name' => $tag],
            $tags['order'] ?? []
        );
    }

    /**
     * @param array<mixed> $context
     *
     * @inheritDoc
     */
    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->openApiFactory)($context);

        $schemas = new ArrayObject();
        $pathsModel = $openApi->getPaths();
        $paths = $pathsModel->getPaths();

        foreach ($paths as $path => $pathItem) {
            foreach (self::OPERATION_METHODS as $operationMethod) {
                $operationMethod = ucfirst(strtolower($operationMethod));
                $getter = sprintf('get%s', $operationMethod);
                $with = sprintf('with%s', $operationMethod);

                /** @var Operation|null $operation */
                $operation = $pathItem->{$getter}();

                if ($operation === null) {
                    continue;
                }

                // todo remove api platform response codes
                $operation = $this->overrideResponses($operation, $schemas);
                $operation = $this->removeEmptyRequestBodies($operation);

                $pathItem = $pathItem->{$with}($operation);
            }

            $pathsModel->addPath($path, $pathItem);
        }

        return $openApi
            ->withPaths($pathsModel)
            ->withTags($this->tags)
            ->withServers($this->servers);
    }

    /**
     * @param ArrayObject<string, mixed> $schemas
     */
    private function overrideResponses(Operation $operation, ArrayObject &$schemas): Operation
    {
        $extensionProperties = $operation->getExtensionProperties();

        if (! isset($extensionProperties['x-message-class'])) {
            return $operation;
        }

        /** @var class-string $messageClass */
        $messageClass = $extensionProperties['x-message-class'];
        $reflectionClass = new ReflectionClass($messageClass);

        if (! $reflectionClass->implementsInterface(HasResponses::class)) {
            return $operation->withResponses([]);
        }

        /** @var class-string<ImmutableRecord> $resourceClass */
        $resourceClass = $extensionProperties['x-resource-class'];
        $operationName = $extensionProperties['x-operation-name'];
        $operationType = $extensionProperties['x-operation-type'];
        $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);

        $responseFormats = $resourceMetadata->getTypedOperationAttribute(
            $operationType,
            $operationName,
            'output_formats',
            $this->formats,
            true
        );
        $responseMimeTypes = $this->flattenMimeTypes($responseFormats);

        /** @var array<Response> $responses */
        $responses = $operation->getResponses();
        $messageClassResponses = $messageClass::__responseSchemasPerStatusCode();

        foreach ($messageClassResponses as $statusCode => $messageClassResponse) {
            $messageResponseArray = $messageClassResponse->toArray();
            $response = $responses[$statusCode] ?? null;

            if (
                $response !== null
                && $messageClass::__defaultStatusCode() === $statusCode
                && ! $messageClass::__overrideDefaultApiPlatformResponse()
            ) {
                $responses[$statusCode] = $response->withDescription(
                    $messageResponseArray['description'] ?? $response->getDescription()
                );

                continue;
            }

            $schema = new Schema('openapi');
            $schema->setDefinitions($schemas);
            $content = new ArrayObject();

            foreach ($responseMimeTypes as $mimeType => $operationFormat) {
                $schema = $this->jsonSchemaFactory->buildSchema(
                    $resourceClass,
                    $operationFormat,
                    Schema::TYPE_OUTPUT,
                    $operationType,
                    $operationName,
                    $schema,
                    [
                        'statusCode' => $statusCode,
                        'isDefaultResponse' => false,
                        'response' => $messageResponseArray,
                    ],
                    false
                );

                $this->appendSchemaDefinitions($schemas, $schema->getDefinitions());
                $content[$mimeType] = new MediaType(new ArrayObject($schema->getArrayCopy(false)));
            }

            $responses[$statusCode] = new Response(
                $messageResponseArray['description'] ?? '',
                $content
            );
        }

        if (isset($responses['default']) && count($responses) > 1) {
            unset($responses['default']);
        }

        foreach ($responses as &$response) {
            $response = $response->withLinks(new ArrayObject([]));
        }

        return $operation->withResponses($responses);
    }

    private function removeEmptyRequestBodies(Operation $operation): Operation
    {
        $requestBody = $operation->getRequestBody();

        if ($requestBody === null) {
            return $operation;
        }

        /** @var ArrayObject<string, MediaType> $content */
        $content = $requestBody->getContent();
        $contentArray = $content->getArrayCopy();

        foreach ($contentArray as $mimeType => $mediaType) {
            $schema = $mediaType->getSchema();

            if ($schema !== null && count($schema) !== 0) {
                continue;
            }

            unset($contentArray[$mimeType]);
        }

        return count($contentArray) === 0
            ? $this->removeRequestBody($operation)
            : $operation;
    }

    private function removeRequestBody(Operation $operation): Operation
    {
        return new Operation(
            $operation->getOperationId(),
            $operation->getTags(),
            $operation->getResponses(),
            $operation->getSummary(),
            $operation->getDescription(),
            $operation->getExternalDocs(),
            $operation->getParameters(),
            null,
            $operation->getCallbacks(),
            $operation->getDeprecated(),
            $operation->getSecurity(),
            $operation->getServers(),
            $operation->getExtensionProperties()
        );
    }

    /**
     * @param array<string, array<string>> $formats
     *
     * @return array<string>
     */
    private function flattenMimeTypes(array $formats): array
    {
        $flattendedMimeTypes = [];
        foreach ($formats as $format => $mimeTypes) {
            foreach ($mimeTypes as $mimeType) {
                $flattendedMimeTypes[$mimeType] = $format;
            }
        }

        return $flattendedMimeTypes;
    }

    /**
     * @param ArrayObject<string, mixed> $schemas
     * @param ArrayObject<string, mixed> $definitions
     */
    private function appendSchemaDefinitions(ArrayObject $schemas, ArrayObject $definitions): void
    {
        foreach ($definitions as $key => $value) {
            $schemas[$key] = $value;
        }
    }
}
