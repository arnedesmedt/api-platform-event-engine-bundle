<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory;

use ApiPlatform\Core\JsonSchema\Schema;
use ApiPlatform\Core\JsonSchema\SchemaFactory as BaseSchemaFactory;
use ApiPlatform\Core\JsonSchema\SchemaFactoryInterface;

use function array_merge;

final class HalSchemaFactory implements SchemaFactoryInterface
{
    private const HREF = [
        'type' => 'object',
        'properties' => [
            'href' => [
                'type' => 'string',
                'readOnly' => true,
            ],
        ],
    ];

    private const BASE_ROOT_PROPS = [
        '_links' => [
            'type' => 'object',
            'properties' => [
                'self' => self::HREF,
            ],
        ],
    ];

    private SchemaFactoryInterface $schemaFactory;

    public function __construct(SchemaFactoryInterface $schemaFactory)
    {
        $this->schemaFactory = $schemaFactory;

        if (! ($schemaFactory instanceof BaseSchemaFactory)) {
            return;
        }

        $schemaFactory->addDistinctFormat('hal');
    }

    /**
     * @param Schema<mixed>|null $schema
     * @param array<mixed>|null $serializerContext
     *
     * @return Schema<mixed>
     */
    public function buildSchema(
        string $className,
        string $format = 'jsonhal',
        string $type = Schema::TYPE_OUTPUT,
        ?string $operationType = null,
        ?string $operationName = null,
        ?Schema $schema = null,
        ?array $serializerContext = null,
        bool $forceCollection = false
    ): Schema {
        $schema = $this->schemaFactory->buildSchema(
            $className,
            $format,
            $type,
            $operationType,
            $operationName,
            $schema,
            $serializerContext,
            $forceCollection
        );

        if ($format !== 'jsonhal') {
            return $schema;
        }

        $definitions = $schema->getDefinitions();
        $key = $schema->getRootDefinitionKey();
        if ($key) {
            $definitions[$key]['properties'] = self::BASE_ROOT_PROPS + ($definitions[$key]['properties'] ?? []);

            return $schema;
        }

        $key = $schema->getItemsDefinitionKey();
        if ($key) {
            $definitions[$key]['properties'] = self::BASE_ROOT_PROPS + ($definitions[$key]['properties'] ?? []);
        }

        if (($schema['type'] ?? '') === 'array') {
            // hal:collection
            $items = $schema['items'];
            unset($schema['items']);

            $nullableStringDefinition = ['type' => 'string'];

            switch ($schema->getVersion()) {
                case Schema::VERSION_JSON_SCHEMA:
                    $nullableStringDefinition = ['type' => ['string', 'null']];
                    break;
                case Schema::VERSION_OPENAPI:
                    $nullableStringDefinition = ['type' => 'string', 'nullable' => true];
                    break;
            }

            $baseProps = self::BASE_ROOT_PROPS;
            $baseProps['_links']['required'] = [
                'self',
                'item',
            ];
            $baseProps['_links']['properties']['item'] = [
                'type' => 'array',
                'items' => self::HREF,
            ];
            $baseProps['_links']['properties']['first'] = self::HREF;
            $baseProps['_links']['properties']['last'] = self::HREF;
            $baseProps['_links']['properties']['prev'] = self::HREF;
            $baseProps['_links']['properties']['next'] = self::HREF;

            $schema['type'] = 'object';
            $schema['required'] = [
                '_embedded',
                '_links',
            ];

            $schema['properties'] = array_merge(
                $baseProps,
                [
                    'totalItems' => [
                        'type' => 'integer',
                        'minimum' => 0,
                    ],
                    'itemsPerPage' => [
                        'type' => 'integer',
                        'minimum' => 0,
                    ],
                    '_embedded' => [
                        'type' => 'object',
                        'properties' => [
                            'item' => [
                                'type' => 'array',
                                'items' => $items,
                            ],
                        ],
                    ],
                ]
            );

            return $schema;
        }

        return $schema;
    }
}
