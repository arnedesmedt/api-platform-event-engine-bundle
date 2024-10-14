<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataCollectionFactory;

use ADS\Bundle\EventEngineBundle\MetadataExtractor\ExceptionExtractor;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use ArrayObject;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use TeamBlue\Exception\HttpException\HttpException;
use Traversable;

use function array_map;
use function count;

#[AsDecorator(
    decorates: 'api_platform.metadata.resource.metadata_collection_factory',
)]
class ExceptionsResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $decorated,
        private readonly ExceptionExtractor $exceptionExtractor,
    ) {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $resourceMetadataCollection = $this->decorated->create($resourceClass);

        foreach ($resourceMetadataCollection as $resourceMetadata) {
            $operations = $resourceMetadata->getOperations();

            /** @var Traversable<string, HttpOperation>|null $iterator */
            $iterator = $operations?->getIterator();
            foreach ($iterator ?? [] as $operationName => $operation) {
                /** @var array{'class'?: class-string} $input */
                $input = $operation->getInput();
                $messageClass = $input['class'] ?? null;

                if ($messageClass === null) {
                    continue;
                }

                $exceptionClasses = $this->exceptionExtractor->extract($messageClass);

                if ($exceptionClasses === []) {
                    continue;
                }

                /** @var Operation $openApi */
                $openApi = $operation->getOpenapi() ?? new Operation();

                /** @var array<int, array<string>> $schemaNamesPerStatusCode */
                $schemaNamesPerStatusCode = [];
                /** @var class-string<HttpException> $exceptionClass */
                foreach ($exceptionClasses as $exceptionClass) {
                    $exception = (new ReflectionClass($exceptionClass))->newInstanceWithoutConstructor();
                    $statusCode = $exception->getStatusCode();
                    $schemaName = $exceptionClass::__type();

                    if (! isset($schemaNamesPerStatusCode[$statusCode])) {
                        $schemaNamesPerStatusCode[$statusCode] = [];
                    }

                    $schemaNamesPerStatusCode[$statusCode][] = $schemaName;
                }

                foreach ($schemaNamesPerStatusCode as $statusCode => $schemaNames) {
                    $openApi->addResponse(
                        new Response(
                            content: new ArrayObject(
                                [
                                    'application/json' => [
                                        'schema' => count($schemaNames) === 1
                                            ? ['$ref' => '#/components/schemas/' . $schemaNames[0]]
                                            : [
                                                'oneOf' => array_map(
                                                    static fn (string $schemaName): array => [
                                                        '$ref' => '#/components/schemas/' . $schemaName,
                                                    ],
                                                    $schemaNames,
                                                ),
                                            ],
                                    ],
                                ],
                            ),
                        ),
                        $statusCode,
                    );
                }

                $operations?->add($operationName, $operation->withOpenapi($openApi));
            }
        }

        return $resourceMetadataCollection;
    }
}
