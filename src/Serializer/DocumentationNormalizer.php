<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\Bundle\ApiPlatformEventEngineBundle\ApiResource\ChangeApiResource;
use ADS\Bundle\ApiPlatformEventEngineBundle\Config;
use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\ApiPlatformMappingException;
use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\Documentation\Documentation;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ApiPlatform\Core\PathResolver\OperationPathResolverInterface;
use ApiPlatform\Core\Swagger\Serializer\DocumentationNormalizer as SwaggerDocumentationNormalizer;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use EventEngine\Schema\TypeSchema;
use ReflectionClass;
use stdClass;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use function array_merge;
use function strtolower;

final class DocumentationNormalizer implements NormalizerInterface
{
    private ResourceMetadataFactoryInterface $resourceMetadataFactory;
    private OperationPathResolverInterface $operationPathResolver;
    /** @var array<string, array<string, array<string, string>>> */
    private array $messageMapping;
    /** @var array<string, array<string, string>> */
    private array $operationMapping;
    private string $url;

    public function __construct(
        ResourceMetadataFactoryInterface $resourceMetadataFactory,
        OperationPathResolverInterface $operationPathResolver,
        Config $config,
        string $url
    ) {
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->operationPathResolver = $operationPathResolver;
        $this->messageMapping = $config->messageMapping();
        $this->operationMapping = $config->operationMapping();
        $this->url = $url;
    }

    /**
     * @param mixed $object
     * @param array<mixed> $context
     *
     * @return mixed
     */
    public function normalize($object, ?string $format = null, array $context = [])
    {
        $messages = $this->messages($object);

        $paths = $this->paths($messages);

        return $this->buildSchema();
    }

    /**
     * @param mixed $data
     */
    public function supportsNormalization($data, ?string $format = null) : bool
    {
        return $format === SwaggerDocumentationNormalizer::FORMAT && $data instanceof Documentation;
    }

    /**
     * @return array<class-string, mixed>
     */
    private function messages(Documentation $documentation) : array
    {
        $messages = [];
        foreach ($documentation->getResourceNameCollection() as $class) {
            /** @var class-string $class */
            $resourceMetadata = $this->resourceMetadataFactory->create($class);

            $reflectionClass = new ReflectionClass($class);

            $resourceClass = $reflectionClass->implementsInterface(ChangeApiResource::class)
                ? $class::__newApiResource()
                : $class;

            $messages = array_merge($messages, $this->resourceMessages($resourceClass, $resourceMetadata));
        }

        return $messages;
    }

    /**
     * @param class-string $resourceClass
     *
     * @return array<class-string, mixed>
     */
    private function resourceMessages(string $resourceClass, ResourceMetadata $resourceMetadata) : array
    {
        $messages = [];

        foreach ($resourceMetadata->getItemOperations() ?? [] as $itemOperationName => $itemOperation) {
            /** @var class-string|null $messageClass */
            $messageClass = $this->messageMapping[$resourceClass][OperationType::ITEM][$itemOperationName] ?? null;

            if (! $messageClass) {
                continue;
            }

            $messages[$messageClass] = $itemOperation;
        }

        foreach ($resourceMetadata->getCollectionOperations() ?? [] as $collectionOperationName => $collectionOperation) {
            /** @var class-string|null $messageClass */
            $messageClass = $this->messageMapping[$resourceClass][OperationType::COLLECTION][$collectionOperationName] ?? null;

            if (! $messageClass) {
                continue;
            }

            $messages[$messageClass] = $collectionOperation;
        }

        return $messages;
    }

    /**
     * @param array<class-string, mixed> $messages
     *
     * @return array<mixed>
     */
    private function paths(array $messages) : array
    {
        $paths = [];

        $schemas = $this->schemas($messages);

        foreach ($messages as $messageClass => $operation) {
            if (! isset($this->operationMapping[$messageClass])) {
                throw ApiPlatformMappingException::noOperationFound($messageClass);
            }

            $operationMapping = $this->operationMapping[$messageClass];
            /** @var class-string $resourceClass */
            $resourceClass = $operationMapping['resource'];
            $shortName = (new ReflectionClass($resourceClass))->getShortName();

            $path = $this->operationPathResolver->resolveOperationPath(
                $shortName,
                $operation,
                $operationMapping['operationType']
            );

            if (! isset($paths[$path])) {
                $paths[$path] = [];
            }

            $schema = $schemas[$messageClass];

            $paths[$path][strtolower($operation['method'])] = array_merge(
                $operation['openapi_context'],
                [
                    'operationId' => $operationMapping['operationName'],
                    'requestBody' => [
                        'content' => [
                            'application/json' => ['schema' => $schema],
                        ],
                    ],
                ]
            );
        }

        return $paths;
    }

    /**
     * @param array<class-string, mixed> $messages
     *
     * @return array<TypeSchema>
     */
    private function schemas(array $messages) : array
    {
        $schemas = [];

        foreach ($messages as $messageClass => $operation) {
            $reflectionClass = new ReflectionClass($messageClass);

            if (! $reflectionClass->implementsInterface(JsonSchemaAwareRecord::class)) {
                continue;
            }

            $schemas[$messageClass] = $messageClass::__schema();
        }

        return $schemas;
    }

    /**
     * @return array<mixed>
     */
    private function buildSchema() : array
    {
        return [
            'openapi' => '3.0.3',
            'servers' => [
                'description' => 'PAAS API',
                'url' => $this->url,
            ],
            'info' => [
                'title' => 'PAAS API',
                'description' => '<p>Platforms as a service API</p>
                    <p style=\'color:red\'>You need to authorize to view the available operations for the logged in user.</p>
                ',
                'version' => 'v0.1.0',
            ],
            'tags' => [],
            'paths' => [],
            'security' => [
                [
                    'passwordNoScope' => [],
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'passwordNoScope' => [
                        'type' => 'oauth2',
                        'flows' => [
                            'password' => [
                                'tokenUrl' => '/oauth/token',
                                'refreshUrl' => '/oauth/refresh',
                                'scopes' => new stdClass(),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
