<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataCollectionFactory;

use ADS\Bundle\EventEngineBundle\Response\HasResponses;
use ADS\ValueObjects\Implementation\ListValue\ListValue;
use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use EventEngine\JsonSchema\JsonSchemaAwareCollection;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use InvalidArgumentException;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;

use function array_combine;
use function array_filter;
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
    ) {
        $this->docBlockFactory = DocBlockFactory::createInstance();
    }

    /**
     * @param class-string $resourceClass
     */
    public function create(string $resourceClass): ResourceMetadataCollection
    {
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
                if (! $reflectionClass->implementsInterface(HasResponses::class)) {
                    continue;
                }

                $openApiContext = $operation->getOpenapiContext();
                /** @var array<string, array<string>> $outputFormats */
                $outputFormats = $operation->getOutputFormats();
                $outputFormats = $this->flattenMimeTypes($outputFormats);
                $responseClasses = $messageClass::__responseClassesPerStatusCode();
                $defaultStatusCode = $messageClass::__defaultStatusCode();
                $openApiContext['responses'] = array_filter(
                    array_combine(
                        array_keys($responseClasses),
                        array_map(
                            function (
                                $responseClass,
                                $statusCode
                            ) use (
                                $outputFormats,
                                $operation,
                                $defaultStatusCode
                            ) {
                                $responseReflectionClass = new ReflectionClass($responseClass);

                                if (
                                    ! $responseReflectionClass->implementsInterface(JsonSchemaAwareRecord::class)
                                    && ! $responseReflectionClass->implementsInterface(JsonSchemaAwareCollection::class)
                                ) {
                                    return null;
                                }

                                $serializerContext = $defaultStatusCode === $statusCode
                                    ? $operation->getNormalizationContext()
                                    : null;

                                $forceCollection = false;
                                if (in_array(ListValue::class, class_parents($responseClass) ?: [])) {
                                    $responseClass = $responseClass::itemType();
                                    $forceCollection = true;
                                }

                                try {
                                    $docBlock = $this->docBlockFactory->create($responseReflectionClass);
                                } catch (InvalidArgumentException) {
                                    $docBlock = null;
                                }

                                return [
                                    'description' => $docBlock?->getSummary() ?? '',
                                    'content' => array_combine(
                                        array_keys($outputFormats),
                                        array_map(
                                            function (
                                                string $format
                                            ) use (
                                                $responseClass,
                                                $forceCollection,
                                                $serializerContext,
                                                $operation
                                            ) {
                                                $schema = $this->schemaFactory->buildSchema(
                                                    $responseClass,
                                                    $format,
                                                    Schema::TYPE_OUTPUT,
                                                    (new HttpOperation(
                                                        $operation->getMethod() ?? HttpOperation::METHOD_GET
                                                    )
                                                    ),
                                                    null,
                                                    $serializerContext,
                                                    $forceCollection
                                                );

                                                return ['schema' => $schema->getArrayCopy(false)];
                                            },
                                            $outputFormats
                                        )
                                    ),
                                    'headers' => null,
                                    'links' => null,
                                ];
                            },
                            $responseClasses,
                            array_keys($responseClasses)
                        )
                    )
                );

                $operation = $operation->withOpenapiContext($openApiContext);

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
