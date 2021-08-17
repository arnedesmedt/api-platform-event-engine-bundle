<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Documentation;

use ApiPlatform\Core\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\Core\OpenApi\Model\Server;
use ApiPlatform\Core\OpenApi\OpenApi;

use function array_map;

final class OpenApiFactory implements OpenApiFactoryInterface
{
    private OpenApiFactoryInterface $openApiFactory;
    /** @var Server[] */
    private array $servers;
    /** @var array<mixed> */
    private array $tags;

    /**
     * @param array<mixed> $servers
     * @param array<mixed> $tags
     */
    public function __construct(
        OpenApiFactoryInterface $openApiFactory,
        array $servers = [],
        array $tags = []
    ) {
        $this->openApiFactory = $openApiFactory;
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

        // todo remove api platform response codes
        $this->overrideOperationResponses($openApi);

        return $openApi
            ->withTags($this->tags)
            ->withServers($this->servers);
    }

    private function overrideOperationResponses(OpenApi &$openApi): void
    {
//        foreach ($openApi->getPaths()->getPaths() as $path => &$pathItem) {
//            foreach (self::OPERATIONS as $operationName) {
//                $operationName = ucfirst(strtolower($operationName));
//                $getter = sprintf('get%s', $operationName);
//                $with = sprintf('with%s', $operationName);
//
//                /** @var Operation|null $operation */
//                $operation = $pathItem->{$getter}();
//
//                if ($operation === null) {
//                    continue;
//                }
//
//                $operationId = $operation->getOperationId();
//                $operationIdMapping = $this->config->operationIdMapping();
//
//                if (! isset($operationIdMapping[$operationId])) {
//                    continue;
//                }
//
//                /** @var class-string<ApiPlatformMessage> $messageClass */
//                $messageClass = $operationIdMapping[$operationId];
//                $reflectionClass = new ReflectionClass($messageClass);
//
//                if (! $reflectionClass->implementsInterface(HasResponses::class)) {
//                    $pathItem = $pathItem->{$with}($operation->withResponses([]));
//                    continue;
//                }
//
//                $responseSchemasPerStatusCode = $messageClass::__responseSchemasPerStatusCode();
//
//                $pathItem = $pathItem->{$with}(
//                    $operation->withResponses(
//                        array_map(
//                            static function (TypeSchema $response) {
//                                return [
//                                    'description' => $response instanceof AnnotatedType
//                                        ? $response->toArray()['description'] ?? ''
//                                        : '',
//                                    'content' => [
//                                        'application/json' => [
//                                            'schema' => OpenApiSchemaFactory::toOpenApiSchema(
//                                                $response->toArray()
//                                            ),
//                                        ],
//                                    ],
//                                ];
//                            },
//
//                        )
//                    )
//                );
//            }
//        }
    }
}
