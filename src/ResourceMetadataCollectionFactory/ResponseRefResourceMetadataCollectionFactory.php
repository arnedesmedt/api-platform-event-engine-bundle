<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataCollectionFactory;

use ADS\Bundle\EventEngineBundle\MetadataExtractor\ResponseExtractor;
use ADS\Exception\MetadataExtractor\ThrowsExtractor;
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
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

use function array_filter;
use function class_parents;
use function in_array;
use function is_subclass_of;
use function reset;

final class ResponseRefResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    private DocBlockFactory $docBlockFactory;

    public function __construct(
        private ResourceMetadataCollectionFactoryInterface $decorated,
        private SchemaFactoryInterface $schemaFactory,
        private readonly ResponseExtractor $responseExtractor,
        private readonly ThrowsExtractor $throwsExtractor,
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
                $responseClassesPerStatusCode = [];
                $defaultResponseClass = $this->responseExtractor
                    ->defaultResponseClassFromReflectionClass($reflectionClass);
                $defaultStatusCode = $this->responseExtractor
                    ->defaultStatusCodeFromReflectionClass($reflectionClass);
                $responseClassesPerStatusCode[$defaultStatusCode] = [$defaultResponseClass];

                $openApi = $operation->getOpenapi();
                if (! $openApi instanceof OpenApiOperation) {
                    $openApi = new OpenApiOperation();
                }

                $responseReflectionClass = new ReflectionClass($defaultResponseClass);
                if (
                    ! $responseReflectionClass->implementsInterface(JsonSchemaAwareRecord::class)
                    && ! $responseReflectionClass->implementsInterface(JsonSchemaAwareCollection::class)
                ) {
                    continue;
                }

                $exceptions = $this->throwsExtractor->exceptionsFromReflectionClass($reflectionClass);
                $httpExceptions = array_filter(
                    $exceptions,
                    static fn (string $exception) => is_subclass_of($exception, HttpExceptionInterface::class),
                );

                foreach ($httpExceptions as $httpException) {
                    /** @var ReflectionClass<HttpExceptionInterface> $httpExceptionReflectionClass */
                    $httpExceptionReflectionClass = (new ReflectionClass($httpException))->getParentClass();
                    $statusCode = $httpExceptionReflectionClass->getProperty('statusCode')->getDefaultValue();
                    if (! isset($responseClassesPerStatusCode[$statusCode])) {
                        $responseClassesPerStatusCode[$statusCode] = [];
                    }

                    $responseClassesPerStatusCode[$statusCode][] = $httpException;
                }

                foreach ($responseClassesPerStatusCode as $statusCode => $responseClasses) {
                    $serializerContext = $defaultStatusCode === $statusCode
                        ? $operation->getNormalizationContext()
                        : null;

                    /** @var class-string<JsonSchemaAwareRecord> $defaultResponseClass */
                    $defaultResponseClass = reset($responseClasses);
                    $responseReflectionClass = new ReflectionClass($defaultResponseClass);

                    try {
                        $docBlock = $this->docBlockFactory->create($responseReflectionClass);
                    } catch (InvalidArgumentException) {
                        $docBlock = null;
                    }

                    /** @var ArrayObject<string, array<string, mixed>> $content */
                    $content = new ArrayObject();
                    foreach ($outputFormats as $outputFormat) {
                        $schemas = [];
                        foreach ($responseClasses as $responseClass) {
                            $forceCollection = false;
                            if (in_array(ListValue::class, class_parents($responseClass) ?: [])) {
                                $responseClass = $responseClass::itemType();
                                $forceCollection = true;
                            }

                            $schemas[] = $this->schemaFactory
                                ->buildSchema(
                                    $responseClass,
                                    $outputFormat,
                                    Schema::TYPE_OUTPUT,
                                    (new HttpOperation($operation->getMethod())),
                                    null,
                                    $serializerContext,
                                    $forceCollection,
                                )
                                ->getArrayCopy(false);
                        }

                        $content[$outputFormat] = ['schema' => ['oneOf' => $schemas]];
                    }

                    $openApi->addResponse(
                        new Response(description: $docBlock?->getSummary() ?? '', content: $content),
                        $statusCode,
                    );
                }

                $operation = $operation->withOpenapi($openApi);
                $operations?->add($operationId, $operation);
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
