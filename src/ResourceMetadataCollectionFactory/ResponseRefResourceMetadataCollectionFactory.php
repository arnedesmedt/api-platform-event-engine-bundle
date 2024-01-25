<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataCollectionFactory;

use ADS\Bundle\EventEngineBundle\MetadataExtractor\ResponseExtractor;
use ADS\ValueObjects\Implementation\ListValue\ListValue;
use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response;
use ArrayObject;
use EventEngine\JsonSchema\JsonSchemaAwareCollection;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use InvalidArgumentException;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;

use function array_combine;
use function array_keys;
use function array_map;
use function class_parents;
use function in_array;

final class ResponseRefResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    private DocBlockFactory $docBlockFactory;

    public function __construct(
        private ResourceMetadataCollectionFactoryInterface $decorated,
        private SchemaFactoryInterface $schemaFactory,
        private readonly ResponseExtractor $responseExtractor,
    ) {
        $this->docBlockFactory = DocBlockFactory::createInstance();
    }

    /** @param class-string $resourceClass */
    public function create(string $resourceClass): ResourceMetadataCollection
    {
        // todo move this to event engine message resource metadata collection factory
        $resourceMetadataCollection = $this->decorated->create($resourceClass);

        /** @var ApiResource $resource */
        foreach ($resourceMetadataCollection as $resource) {
            $operations = $resource->getOperations();

            /** @var string $operationId */
            foreach ($operations ?? [] as $operationId => $operation) {
                if (! $operation instanceof HttpOperation) {
                    continue;
                }

                $messageClass = $operation->getInput()['class'] ?? null;

                if ($messageClass === null) {
                    continue;
                }

                $reflectionClass = new ReflectionClass($messageClass);
                if (! $this->responseExtractor->hasResponsesFromReflectionClass($reflectionClass)) {
                    continue;
                }

                /** @var array<string, array<string>> $outputFormats */
                $outputFormats = $operation->getOutputFormats();
                $outputFormats = $this->flattenMimeTypes($outputFormats);
                $statusCode = $this->responseExtractor->defaultStatusCodeFromReflectionClass($reflectionClass);
                $responseClass = $this->responseExtractor->defaultResponseClassFromReflectionClass($reflectionClass);

                $openApi = $operation->getOpenapi();
                if (! $openApi instanceof OpenApiOperation) {
                    $openApi = new OpenApiOperation();
                }

                $responseReflectionClass = new ReflectionClass($responseClass);

                if (
                    ! $responseReflectionClass->implementsInterface(JsonSchemaAwareRecord::class)
                    && ! $responseReflectionClass->implementsInterface(JsonSchemaAwareCollection::class)
                ) {
                    continue;
                }

                $serializerContext = $operation->getNormalizationContext();

                $forceCollection = false;
                if (in_array(ListValue::class, class_parents($responseClass) ?: [])) {
                    // @phpstan-ignore-next-line
                    $responseClass = $responseClass::itemType();
                    $forceCollection = true;
                }

                try {
                    $docBlock = $this->docBlockFactory->create($responseReflectionClass);
                } catch (InvalidArgumentException) {
                    $docBlock = null;
                }

                $openApi->addResponse(
                    new Response(
                        description: $docBlock?->getSummary() ?? '',
                        content: new ArrayObject(
                            array_combine(
                                array_keys($outputFormats),
                                array_map(
                                    function (
                                        string $format,
                                    ) use (
                                        $responseClass,
                                        $forceCollection,
                                        $serializerContext,
                                        $operation,
                                    ) {
                                        $schema = $this->schemaFactory->buildSchema(
                                            $responseClass,
                                            $format,
                                            Schema::TYPE_OUTPUT,
                                            (new HttpOperation($operation->getMethod())),
                                            null,
                                            $serializerContext,
                                            $forceCollection,
                                        );

                                        return ['schema' => $schema->getArrayCopy(false)];
                                    },
                                    $outputFormats,
                                ),
                            ),
                        ),
                    ),
                    $statusCode,
                );

                $operation = $operation->withOpenapi($openApi);

                if (! $operations) {
                    break;
                }

                $operations->add($operationId, $operation);
            }
        }

        return $resourceMetadataCollection;
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
}
