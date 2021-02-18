<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Documentation;

use ADS\Bundle\ApiPlatformEventEngineBundle\Config;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataFactory\DocumentationResourceMetadataFactory;
use ADS\Bundle\EventEngineBundle\Response\HasResponses;
use ApiPlatform\Core\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\Core\OpenApi\Model\Operation;
use ApiPlatform\Core\OpenApi\Model\PathItem;
use ApiPlatform\Core\OpenApi\Model\Paths;
use ApiPlatform\Core\OpenApi\OpenApi;
use EventEngine\JsonSchema\AnnotatedType;
use EventEngine\Schema\TypeSchema;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;

use function array_map;
use function sprintf;
use function strtolower;
use function ucfirst;

final class OpenApiFactory implements OpenApiFactoryInterface
{
    public const OPERATIONS = [
        Request::METHOD_GET,
        Request::METHOD_POST,
        Request::METHOD_DELETE,
        Request::METHOD_PUT,
        Request::METHOD_PATCH,
        Request::METHOD_HEAD,
        Request::METHOD_OPTIONS,
        Request::METHOD_TRACE,
    ];

    private OpenApiFactoryInterface $openApiFactory;
    private Config $config;

    public function __construct(
        OpenApiFactoryInterface $openApiFactory,
        Config $config
    ) {
        $this->openApiFactory = $openApiFactory;
        $this->config = $config;
    }

    /**
     * @param array<mixed> $context
     *
     * @inheritDoc
     */
    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->openApiFactory)($context);

        return $openApi
            ->withPaths($this->pathsWithResponses($openApi));
    }

    private function pathsWithResponses(OpenApi $openApi): Paths
    {
        $paths = new Paths();

        /** @var PathItem $pathItem */
        foreach ($openApi->getPaths()->getPaths() as $path => $pathItem) {
            foreach (self::OPERATIONS as $operationName) {
                $operationName = ucfirst(strtolower($operationName));
                $getter = sprintf('get%s', $operationName);
                $with = sprintf('with%s', $operationName);

                /** @var Operation|null $operation */
                $operation = $pathItem->{$getter}();

                if ($operation === null) {
                    continue;
                }

                $operationId = $operation->getOperationId();
                $operationIdMapping = $this->config->operationIdMapping();

                if (! isset($operationIdMapping[$operationId])) {
                    continue;
                }

                /** @var class-string<ApiPlatformMessage> $messageClass */
                $messageClass = $operationIdMapping[$operationId];
                $reflectionClass = new ReflectionClass($messageClass);

                if (! $reflectionClass->implementsInterface(HasResponses::class)) {
                    $pathItem = $pathItem->{$with}($operation->withResponses([]));
                    continue;
                }

                $pathItem = $pathItem->{$with}(
                    $operation->withResponses(
                        array_map(
                            static function (TypeSchema $response) {
                                return [
                                    'description' => $response instanceof AnnotatedType
                                        ? $response->toArray()['description'] ?? ''
                                        : '',
                                    'content' => [
                                        'application/json' => [
                                            'schema' => DocumentationResourceMetadataFactory::toOpenApiSchema(
                                                $response->toArray()
                                            ),
                                        ],
                                    ],
                                ];
                            },
                            $messageClass::__responseSchemasPerStatusCode()
                        )
                    )
                );
            }

            $paths->addPath($path, $pathItem);
        }

        return $paths;
    }
}
