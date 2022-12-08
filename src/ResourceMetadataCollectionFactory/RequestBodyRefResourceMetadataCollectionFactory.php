<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataCollectionFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\CallbackMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory\MessageSchemaFactory;
use ADS\Bundle\EventEngineBundle\Command\Command;
use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;

use function array_combine;
use function array_keys;
use function array_map;

final class RequestBodyRefResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(
        private ResourceMetadataCollectionFactoryInterface $decorated,
        private SchemaFactoryInterface $schemaFactory,
    ) {
    }

    /**
     * @param class-string $resourceClass
     */
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

                $input = $operation->getInput();
                $messageClass = $input['class'] ?? null;

                if ($messageClass === null) {
                    continue;
                }

                $reflectionClass = new ReflectionClass($messageClass);
                if (
                    ! $reflectionClass->implementsInterface(Command::class)
                    || ($operation->getMethod() === Request::METHOD_DELETE
                        && ! $reflectionClass->implementsInterface(CallbackMessage::class)
                    )
                ) {
                    continue;
                }

                $openApiContext = $operation->getOpenapiContext();
                /** @var array<string, array<string>> $inputFormats */
                $inputFormats = $operation->getInputFormats();
                $inputFormats = $this->flattenMimeTypes($inputFormats);
                $forceCollection = false;

                MessageSchemaFactory::updateOperationForLists(
                    $messageClass,
                    $reflectionClass,
                    $input,
                    $operation,
                    $forceCollection
                );

                $openApiContext['requestBody'] = [
                    'description' => '', // TODO GET DESCRIPTION FROM THE CLASS DOCBLOCK
                    'content' => array_combine(
                        array_keys($inputFormats),
                        array_map(
                            function (string $format) use ($resourceClass, $operation, $forceCollection) {
                                $schema = $this->schemaFactory->buildSchema(
                                    $resourceClass,
                                    $format,
                                    Schema::TYPE_INPUT,
                                    $operation,
                                    null,
                                    null,
                                    $forceCollection
                                );

                                return ['schema' => $schema->getArrayCopy(false)];
                            },
                            $inputFormats
                        )
                    ),
                    'required' => true,
                ];

                $operation = $operation
                    ->withInput($input)
                    ->withOpenapiContext($openApiContext);

                if ($operations === null) {
                    continue;
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
