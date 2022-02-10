<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Documentation;

use ADS\Bundle\EventEngineBundle\Response\HasResponses;
use ApiPlatform\Core\Api\FilterLocatorTrait;
use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\DataProvider\PaginationOptions;
use ApiPlatform\Core\JsonSchema\Schema;
use ApiPlatform\Core\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Core\JsonSchema\TypeFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ApiPlatform\Core\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\Core\OpenApi\Model;
use ApiPlatform\Core\OpenApi\Model\ExternalDocumentation;
use ApiPlatform\Core\OpenApi\Model\Response;
use ApiPlatform\Core\OpenApi\Model\Server;
use ApiPlatform\Core\OpenApi\OpenApi;
use ApiPlatform\Core\OpenApi\Options;
use ApiPlatform\Core\Operation\Factory\SubresourceOperationFactoryInterface;
use ApiPlatform\Core\PathResolver\OperationPathResolverInterface;
use ArrayObject;
use LogicException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Symfony\Component\PropertyInfo\Type;

use function array_filter;
use function array_keys;
use function array_map;
use function array_merge;
use function ceil;
use function count;
use function floor;
use function in_array;
use function lcfirst;
use function preg_match;
use function preg_replace;
use function similar_text;
use function sprintf;
use function strpos;
use function strtolower;
use function substr;
use function trim;
use function ucfirst;
use function usort;

use const ARRAY_FILTER_USE_KEY;

/**
 * Generates an Open API v3 specification.
 */
final class OpenApiFactory implements OpenApiFactoryInterface
{
    use FilterLocatorTrait;

    private ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory;
    private ResourceMetadataFactoryInterface $resourceMetadataFactory;
    private OperationPathResolverInterface $operationPathResolver;
    private SubresourceOperationFactoryInterface $subresourceOperationFactory;
    private SchemaFactoryInterface $jsonSchemaFactory;
    private TypeFactoryInterface $jsonSchemaTypeFactory;
    private Options $openApiOptions;
    private PaginationOptions $paginationOptions;
    /**
     * @var mixed[]
     * @readonly
     */
    private array $formats;

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
     * @param array<mixed> $formats
     * @param array<array<string, string>> $servers
     * @param array<string, array<string>> $tags
     */
    public function __construct(
        ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory,
        ResourceMetadataFactoryInterface $resourceMetadataFactory,
        SchemaFactoryInterface $jsonSchemaFactory,
        TypeFactoryInterface $jsonSchemaTypeFactory,
        OperationPathResolverInterface $operationPathResolver,
        ContainerInterface $filterLocator,
        SubresourceOperationFactoryInterface $subresourceOperationFactory,
        Options $openApiOptions,
        PaginationOptions $paginationOptions,
        array $formats = [],
        array $servers = [],
        array $tags = []
    ) {
        $this->resourceNameCollectionFactory = $resourceNameCollectionFactory;
        $this->jsonSchemaFactory = $jsonSchemaFactory;
        $this->jsonSchemaTypeFactory = $jsonSchemaTypeFactory;
        $this->formats = $formats;
        $this->setFilterLocator($filterLocator, true);
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->operationPathResolver = $operationPathResolver;
        $this->openApiOptions = $openApiOptions;
        $this->paginationOptions = $paginationOptions;
        $this->subresourceOperationFactory = $subresourceOperationFactory;

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
     * {@inheritdoc}
     *
     * @param array<string, mixed> $context
     */
    public function __invoke(array $context = []): OpenApi
    {
        $info = new Model\Info(
            $this->openApiOptions->getTitle(),
            $this->openApiOptions->getVersion(),
            trim($this->openApiOptions->getDescription())
        );
        $paths = new Model\Paths();
        $schemas = [];

        foreach ($this->resourceNameCollectionFactory->create() as $resourceClass) {
            $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);

            // Items needs to be parsed first to be able to reference the lines from the collection operation
            $itemOperationSchemas = $this->collectPaths(
                $resourceMetadata,
                $resourceClass,
                OperationType::ITEM,
                $context,
                $paths,
                $schemas
            );

            $schemas += $itemOperationSchemas;

            $collectionOperationSchemas = $this->collectPaths(
                $resourceMetadata,
                $resourceClass,
                OperationType::COLLECTION,
                $context,
                $paths,
                $schemas
            );

            $subresourceOperationSchemas = $this->collectPaths(
                $resourceMetadata,
                $resourceClass,
                OperationType::SUBRESOURCE,
                $context,
                $paths,
                $schemas
            );

            $schemas += $collectionOperationSchemas;
        }

        $securitySchemes = $this->getSecuritySchemes();
        $securityRequirements = [];

        foreach (array_keys($securitySchemes) as $key) {
            $securityRequirements[] = [$key => []];
        }

        return new OpenApi(
            $info,
            $this->servers,
            $paths,
            new Model\Components(
                new ArrayObject($schemas),
                new ArrayObject(),
                new ArrayObject(),
                new ArrayObject(),
                new ArrayObject(),
                new ArrayObject(),
                new ArrayObject($securitySchemes)
            ),
            $securityRequirements,
            $this->tags
        );
    }

    /**
     * @param array<string, mixed> $context
     * @param array<mixed> $schemas
     *
     * @return array<mixed>
     */
    private function collectPaths(
        ResourceMetadata $resourceMetadata,
        string $resourceClass,
        string $operationType,
        array $context,
        Model\Paths $paths,
        array $schemas = []
    ): array {
        /** @var string $resourceShortName */
        $resourceShortName = $resourceMetadata->getShortName();
        $operations = $operationType === OperationType::COLLECTION
            ? $resourceMetadata->getCollectionOperations()
            : ($operationType === OperationType::ITEM
                ? $resourceMetadata->getItemOperations()
                : $this->subresourceOperationFactory->create($resourceClass)
            );

        if (! $operations) {
            return $schemas;
        }

        foreach ($operations as $operationName => $operation) {
            $identifiers = (array) ($operation['identified_by'] ?? $resourceMetadata->getAttribute('identified_by'));
            $hasCompositeIdentifiers = count($identifiers) > 1
                ? $resourceMetadata->getAttribute('composite_identifier', true)
                : false;

            if ($hasCompositeIdentifiers) {
                $identifiers = ['id'];
            }

            $path = $this->getPath($resourceShortName, $operationName, $operation, $operationType);
            $method = $resourceMetadata->getTypedOperationAttribute(
                $operationType,
                $operationName,
                'method',
                'GET'
            );

            [$requestMimeTypes, $responseMimeTypes] = $this->getMimeTypes(
                $resourceClass,
                $operationName,
                $operationType,
                $resourceMetadata
            );

            $operationId = $operation['openapi_context']['operationId']
                ?? lcfirst($operationName) . ucfirst($resourceShortName) . ucfirst($operationType);
            $pathItem = $paths->getPath($path) ?: new Model\PathItem();
            $extensionProperties = $this->extensionProperties($operation['openapi_context'] ?? []);
            /** @var class-string|null $messageClass */
            $messageClass = $extensionProperties['x-message-class'] ?? null;
            $reflectionClass = $messageClass ? new ReflectionClass($messageClass) : null;
            $defaultStatuses = [
                'POST' => '201',
                'DELETE' => '204',
            ];

            $messageClassResponses = [$defaultStatuses[$method] ?? '200' => null];
            if ($reflectionClass && $messageClass && $reflectionClass->implementsInterface(HasResponses::class)) {
                $messageClassResponses = $messageClass::__responseSchemasPerStatusCode();
            }

            $operationOutputSchemas = [];
            foreach ($messageClassResponses as $statusCode => $messageClassResponse) {
                $context['response'] = $messageClassResponse ? $messageClassResponse->toArray() : null;
                foreach ($responseMimeTypes as $operationFormat) {
                    $operationOutputSchema = $this->jsonSchemaFactory->buildSchema(
                        $resourceClass,
                        $operationFormat,
                        Schema::TYPE_OUTPUT,
                        $operationType,
                        $operationName,
                        new Schema('openapi'),
                        $context
                    );
                    $schemas += $operationOutputSchema->getDefinitions()->getArrayCopy();
                    $operationOutputSchemas[$operationFormat] = $operationOutputSchema;
                }

                unset($context['response']);
            }

            $parameters = [];
            $responses = [];

            if ($operation['openapi_context']['parameters'] ?? false) {
                foreach ($operation['openapi_context']['parameters'] as $parameter) {
                    $parameters[] = new Model\Parameter(
                        $parameter['name'],
                        $parameter['in'],
                        $parameter['description'] ?? '',
                        $parameter['required'] ?? false,
                        $parameter['deprecated'] ?? false,
                        $parameter['allowEmptyValue'] ?? false,
                        $parameter['schema'] ?? [],
                        $parameter['style'] ?? null,
                        $parameter['explode'] ?? false,
                        $parameter['allowReserved '] ?? false,
                        $parameter['example'] ?? null,
                        isset($parameter['examples']) ? new ArrayObject($parameter['examples']) : null,
                        isset($parameter['content']) ? new ArrayObject($parameter['content']) : null
                    );
                }
            }

            // Set up parameters
            if ($operationType === OperationType::ITEM) {
                foreach ($identifiers as $identifier) {
                    $parameters[] = new Model\Parameter(
                        $identifier,
                        'path',
                        'Resource identifier',
                        true,
                        false,
                        false,
                        ['type' => 'string']
                    );
                }
            } elseif ($operationType === OperationType::COLLECTION && $method === 'GET') {
                $parameters = array_merge(
                    $parameters,
                    $this->getPaginationParameters($resourceMetadata, $operationName),
                    $this->getFiltersParameters($resourceMetadata, $operationName, $resourceClass)
                );
            } elseif ($operationType === OperationType::SUBRESOURCE) {
                // FIXME: In SubresourceOperationFactory identifiers may happen twice
                $added = [];
                foreach ($operation['identifiers'] as $identifier) {
                    if (in_array($identifier[0], $added, true)) {
                        continue;
                    }

                    $added[] = $identifier[0];
                    $parameterShortname = $this->resourceMetadataFactory->create($identifier[1])->getShortName();
                    $parameters[] = new Model\Parameter(
                        $identifier[0],
                        'path',
                        $parameterShortname . ' identifier',
                        true,
                        false,
                        false,
                        ['type' => 'string']
                    );
                }

                if ($operation['collection']) {
                    $parameters = array_merge(
                        $parameters,
                        $this->getPaginationParameters($resourceMetadata, $operationName),
                        $this->getFiltersParameters($resourceMetadata, $operationName, $resourceClass)
                    );
                }
            }

            // Create responses
            if ($reflectionClass && $messageClass && $reflectionClass->implementsInterface(HasResponses::class)) {
                foreach ($messageClassResponses as $statusCode => $messageClassResponse) {
                    $messageResponseArray = $messageClassResponse->toArray();
                    $responses[$statusCode] = new Response(
                        $messageResponseArray['description'] ?? '',
                        $this->buildContent($responseMimeTypes, $operationOutputSchemas)
                    );
                }
            } else {
                $successStatus = (string) $resourceMetadata
                    ->getTypedOperationAttribute(
                        $operationType,
                        $operationName,
                        'status',
                        $defaultStatuses[$method] ?? '200'
                    );

                $responseContent = $this->buildContent($responseMimeTypes, $operationOutputSchemas);
                $description = '';

                switch ($method) {
                    case 'GET':
                        $description = sprintf(
                            '%s %s',
                            $resourceShortName,
                            $operationType === OperationType::COLLECTION ? 'collection' : 'resource'
                        );
                        break;
                    case 'POST':
                        $description = sprintf('%s resource created', $resourceShortName);
                        $responses['400'] = new Model\Response('Invalid input');
                        break;
                    case 'PATCH':
                    case 'PUT':
                        $description = sprintf('%s resource updated', $resourceShortName);
                        $responses['400'] = new Model\Response('Invalid input');
                        break;
                    case 'DELETE':
                        $responseContent = null;
                        $description = sprintf('%s resource deleted', $resourceShortName);
                        break;
                }

                $responses[$successStatus] = new Model\Response($description, $responseContent);

                if ($operationType === OperationType::ITEM) {
                    $responses['404'] = new Model\Response('Resource not found');
                }
            }

            $requestBody = null;
            if ($method === 'PUT' || $method === 'POST' || $method === 'PATCH' || $method === 'DELETE') {
                $operationInputSchemas = [];
                foreach ($requestMimeTypes as $operationFormat) {
                    $operationInputSchema = $this->jsonSchemaFactory->buildSchema(
                        $resourceClass,
                        $operationFormat,
                        Schema::TYPE_INPUT,
                        $operationType,
                        $operationName,
                        new Schema('openapi'),
                        $context
                    );
                    $schemas += $operationInputSchema->getDefinitions()->getArrayCopy();
                    $operationInputSchemas[$operationFormat] = $operationInputSchema;
                }

                $requestBody = new Model\RequestBody(
                    '',
                    $this->buildContent($requestMimeTypes, $operationInputSchemas),
                    true
                );
            }

            $pathItem = $pathItem->{'with' . ucfirst($method)}(new Model\Operation(
                $operationId,
                $operation['openapi_context']['tags'] ?? (
                    $operationType === OperationType::SUBRESOURCE
                        ? $operation['shortNames']
                        : [$resourceShortName]
                ),
                $responses,
                $operation['openapi_context']['summary'] ?? '',
                $operation['openapi_context']['description'] ??
                $this->getPathDescription($resourceShortName, $method, $operationType),
                isset($operation['openapi_context']['externalDocs'])
                    ? new ExternalDocumentation(
                        $operation['openapi_context']['externalDocs']['description'] ?? null,
                        $operation['openapi_context']['externalDocs']['url']
                    )
                    : null,
                $parameters,
                $requestBody,
                isset($operation['openapi_context']['callbacks'])
                    ? new ArrayObject($operation['openapi_context']['callbacks'])
                    : null,
                $operation['openapi_context']['deprecated'] ??
                    (bool) $resourceMetadata->getTypedOperationAttribute(
                        $operationType,
                        $operationName,
                        'deprecation_reason',
                        false,
                        true
                    ),
                $operation['openapi_context']['security'] ?? [],
                $operation['openapi_context']['servers'] ?? null,
                $this->extensionProperties($operation['openapi_context'] ?? [])
            ));

            $paths->addPath($path, $pathItem);
        }

        return $schemas;
    }

    /**
     * @param array<string, string> $responseMimeTypes
     * @param array<string, ArrayObject<string, mixed>>  $operationSchemas
     *
     * @return ArrayObject<string, mixed>
     */
    private function buildContent(array $responseMimeTypes, array $operationSchemas): ArrayObject
    {
        $content = new ArrayObject();

        foreach ($responseMimeTypes as $mimeType => $format) {
            $content[$mimeType] = new Model\MediaType(
                new ArrayObject($operationSchemas[$format]->getArrayCopy())
            );
        }

        return $content;
    }

    /**
     * @return string[][]
     */
    private function getMimeTypes(
        string $resourceClass,
        string $operationName,
        string $operationType,
        ResourceMetadata $resourceMetadata
    ): array {
        $requestFormats = $resourceMetadata->getTypedOperationAttribute(
            $operationType,
            $operationName,
            'input_formats',
            $this->formats,
            true
        );
        $responseFormats = $resourceMetadata->getTypedOperationAttribute(
            $operationType,
            $operationName,
            'output_formats',
            $this->formats,
            true
        );

        $requestMimeTypes = $this->flattenMimeTypes($requestFormats);
        $responseMimeTypes = $this->flattenMimeTypes($responseFormats);

        return [$requestMimeTypes, $responseMimeTypes];
    }

    /**
     * @param array<string, array<string>> $responseFormats
     *
     * @return array<string, string>
     */
    private function flattenMimeTypes(array $responseFormats): array
    {
        $responseMimeTypes = [];
        foreach ($responseFormats as $responseFormat => $mimeTypes) {
            foreach ($mimeTypes as $mimeType) {
                $responseMimeTypes[$mimeType] = $responseFormat;
            }
        }

        return $responseMimeTypes;
    }

    /**
     * Gets the path for an operation.
     *
     * If the path ends with the optional _format parameter, it is removed
     * as optional path parameters are not yet supported.
     *
     * @see https://github.com/OAI/OpenAPI-Specification/issues/93
     *
     * @param array<string, string> $operation
     */
    private function getPath(
        string $resourceShortName,
        string $operationName,
        array $operation,
        string $operationType
    ): string {
        if ($operation['path'] ?? null) {
            return strpos($operation['path'], '/') === 0 ? $operation['path'] : '/' . $operation['path'];
        }

        /** @phpstan-ignore-next-line */
        $path = $this->operationPathResolver->resolveOperationPath(
            $resourceShortName,
            $operation,
            $operationType,
            $operationName
        );

        if (substr($path, -10) === '.{_format}') {
            $path = substr($path, 0, -10);
        }

        return $path;
    }

    private function getPathDescription(string $resourceShortName, string $method, string $operationType): string
    {
        switch ($method) {
            case 'GET':
                $pathSummary = $operationType === OperationType::COLLECTION
                    ? 'Retrieves the collection of %s resources.'
                    : 'Retrieves a %s resource.';
                break;
            case 'POST':
                $pathSummary = 'Creates a %s resource.';
                break;
            case 'PATCH':
                $pathSummary = 'Updates the %s resource.';
                break;
            case 'PUT':
                $pathSummary = 'Replaces the %s resource.';
                break;
            case 'DELETE':
                $pathSummary = 'Removes the %s resource.';
                break;
            default:
                return $resourceShortName;
        }

        return sprintf($pathSummary, $resourceShortName);
    }

    /**
     * Gets parameters corresponding to enabled filters.
     *
     * @return array<int, Model\Parameter>
     */
    private function getFiltersParameters(
        ResourceMetadata $resourceMetadata,
        string $operationName,
        string $resourceClass
    ): array {
        $parameters = [];
        $resourceFilters = $resourceMetadata->getCollectionOperationAttribute(
            $operationName,
            'filters',
            [],
            true
        );

        foreach ($resourceFilters as $filterId) {
            $filter = $this->getFilter($filterId);
            if (! $filter) {
                continue;
            }

            foreach ($filter->getDescription($resourceClass) as $name => $data) {
                $schema = $data['schema'] ?? in_array($data['type'], Type::$builtinTypes, true)
                        ? $this->jsonSchemaTypeFactory->getType(
                            new Type($data['type'], false, null, $data['is_collection'] ?? false)
                        )
                        : ['type' => 'string'];

                $parameters[] = new Model\Parameter(
                    $name,
                    'query',
                    $data['description'] ?? '',
                    $data['required'] ?? false,
                    $data['openapi']['deprecated'] ?? false,
                    $data['openapi']['allowEmptyValue'] ?? true,
                    $schema,
                    $schema['type'] === 'array' && in_array(
                        $data['type'],
                        [Type::BUILTIN_TYPE_ARRAY, Type::BUILTIN_TYPE_OBJECT],
                        true
                    ) ? 'deepObject' : 'form',
                    $schema['type'] === 'array',
                    $data['openapi']['allowReserved'] ?? false,
                    $data['openapi']['example'] ?? null,
                    isset($data['openapi']['examples']) ? new ArrayObject($data['openapi']['examples']) : null
                );
            }
        }

        return $parameters;
    }

    /**
     * @return array<int, Model\Parameter>
     */
    private function getPaginationParameters(ResourceMetadata $resourceMetadata, string $operationName): array
    {
        if (! $this->paginationOptions->isPaginationEnabled()) {
            return [];
        }

        $parameters = [];

        if (
            $resourceMetadata->getCollectionOperationAttribute(
                $operationName,
                'pagination_enabled',
                true,
                true
            )
        ) {
            $parameters[] = new Model\Parameter(
                $this->paginationOptions->getPaginationPageParameterName(),
                'query',
                'The collection page number',
                false,
                false,
                true,
                ['type' => 'integer', 'default' => 1]
            );

            if (
                $resourceMetadata->getCollectionOperationAttribute(
                    $operationName,
                    'pagination_client_items_per_page',
                    $this->paginationOptions->getClientItemsPerPage(),
                    true
                )
            ) {
                $schema = [
                    'type' => 'integer',
                    'default' => $resourceMetadata->getCollectionOperationAttribute(
                        $operationName,
                        'pagination_items_per_page',
                        30,
                        true
                    ),
                    'minimum' => 0,
                ];

                $maxItemsPerPage = $resourceMetadata->getCollectionOperationAttribute(
                    $operationName,
                    'pagination_maximum_items_per_page',
                    null,
                    true
                );

                if ($maxItemsPerPage !== null) {
                    $schema['maximum'] = $maxItemsPerPage;
                }

                $parameters[] = new Model\Parameter(
                    $this->paginationOptions->getItemsPerPageParameterName(),
                    'query',
                    'The number of items per page',
                    false,
                    false,
                    true,
                    $schema
                );
            }
        }

        if (
            $resourceMetadata->getCollectionOperationAttribute(
                $operationName,
                'pagination_client_enabled',
                $this->paginationOptions->getPaginationClientEnabled(),
                true
            )
        ) {
            $parameters[] = new Model\Parameter(
                $this->paginationOptions->getPaginationClientEnabledParameterName(),
                'query',
                'Enable or disable pagination',
                false,
                false,
                true,
                ['type' => 'boolean']
            );
        }

        return $parameters;
    }

    private function getOauthSecurityScheme(): Model\SecurityScheme
    {
        $oauthFlow = new Model\OAuthFlow(
            $this->openApiOptions->getOAuthAuthorizationUrl(),
            $this->openApiOptions->getOAuthTokenUrl(),
            $this->openApiOptions->getOAuthRefreshUrl(),
            new ArrayObject($this->openApiOptions->getOAuthScopes())
        );

        /** @var string $oauthFlowOption */
        $oauthFlowOption = $this->openApiOptions->getOAuthFlow();
        /** @var string $replacement */
        $replacement = preg_replace('/[A-Z]/', ' \\0', lcfirst($oauthFlowOption));
        $description = sprintf(
            'OAuth 2.0 %s Grant',
            strtolower($replacement)
        );

        $implicit = $password = $clientCredentials = $authorizationCode = null;

        switch ($oauthFlowOption) {
            case 'implicit':
                $implicit = $oauthFlow;
                break;
            case 'password':
                $password = $oauthFlow;
                break;
            case 'application':
            case 'clientCredentials':
                $clientCredentials = $oauthFlow;
                break;
            case 'accessCode':
            case 'authorizationCode':
                $authorizationCode = $oauthFlow;
                break;
            default:
                throw new LogicException(
                    'OAuth flow must be one of: implicit, password, clientCredentials, authorizationCode'
                );
        }

        return new Model\SecurityScheme(
            $this->openApiOptions->getOAuthType(),
            $description,
            null,
            null,
            'oauth2',
            null,
            new Model\OAuthFlows($implicit, $password, $clientCredentials, $authorizationCode),
            null
        );
    }

    /**
     * @return array<string, Model\SecurityScheme>
     */
    private function getSecuritySchemes(): array
    {
        $securitySchemes = [];

        if ($this->openApiOptions->getOAuthEnabled()) {
            $securitySchemes['oauth'] = $this->getOauthSecurityScheme();
        }

        foreach ($this->openApiOptions->getApiKeys() as $key => $apiKey) {
            $description = sprintf('Value for the %s %s parameter.', $apiKey['name'], $apiKey['type']);
            $securitySchemes[$key] = new Model\SecurityScheme(
                'apiKey',
                $description,
                $apiKey['name'],
                $apiKey['type'],
                'bearer'
            );
        }

        return $securitySchemes;
    }

    /**
     * @param array<string, mixed> $openApiContext
     *
     * @return array<string, mixed>
     */
    private function extensionProperties(array $openApiContext): array
    {
        return array_filter(
            $openApiContext,
            static fn ($item) => preg_match('/^x-.*$/i', $item),
            ARRAY_FILTER_USE_KEY
        );
    }
}
