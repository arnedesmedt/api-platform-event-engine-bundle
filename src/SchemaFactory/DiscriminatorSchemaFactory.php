<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryAwareInterface;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Util\ResourceClassInfoTrait;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use TeamBlue\JsonImmutableObjects\Polymorphism\Discriminator;

use function array_combine;
use function array_filter;
use function array_map;
use function array_values;
use function sprintf;

#[AsDecorator('api_platform.json_schema.schema_factory', priority: 4)]
final class DiscriminatorSchemaFactory implements SchemaFactoryInterface, SchemaFactoryAwareInterface
{
    use ResourceClassInfoTrait;

    public function __construct(
        #[AutowireDecorated]
        private SchemaFactoryInterface $schemaFactory,
    ) {
        if (! ($this->schemaFactory instanceof SchemaFactoryAwareInterface)) {
            return;
        }

        $this->schemaFactory->setSchemaFactory($this);
    }

    /**
     * @param class-string<Discriminator> $className
     * @param array<string, mixed>|null $serializerContext
     * @param Schema<mixed>|null $schema
     *
     * @return Schema<mixed>
     */
    public function buildSchema(
        string $className,
        string $format = 'json',
        string $type = Schema::TYPE_OUTPUT,
        Operation|null $operation = null,
        Schema|null $schema = null,
        array|null $serializerContext = null,
        bool $forceCollection = false,
    ): Schema {
        $classReflectionClass = new ReflectionClass($className);

        if (! $classReflectionClass->implementsInterface(Discriminator::class)) {
            return $this->schemaFactory->buildSchema(
                $className,
                $format,
                $type,
                $operation,
                $schema,
                $serializerContext,
                $forceCollection,
            );
        }

        $schema ??= new Schema(Schema::VERSION_OPENAPI);

        $definitionName = $className::__type() . ($format === 'json' ? '' : '.' . $format);
        $ref = sprintf(
            $schema->getVersion() === Schema::VERSION_OPENAPI
                ? '#/components/schemas/%s'
                : '#/definitions/%s',
            $definitionName,
        );

        $definitionNames = array_combine(
            $className::jsonSchemaAwareRecords(),
            array_map(
                function (string $modelClass) use ($format, $type, &$schema) {
                    unset($schema['$ref']);
                    $schema = $this->schemaFactory->buildSchema(
                        $modelClass,
                        $format,
                        $type,
                        null,
                        $schema,
                    );

                    return $schema->getArrayCopy(false);
                },
                $className::jsonSchemaAwareRecords(),
            ),
        );

        $schema['$ref'] = $ref;
        $definitions = $schema->getDefinitions();
        $definitions[$definitionName] = [
            'oneOf' => array_values($definitionNames),
            'discriminator' => [
                'propertyName' => $className::propertyName(),
                'mapping' => array_filter(
                    array_map(
                        static fn (string $oneOfClass) => $definitionNames[$oneOfClass]['$ref'] ?? null,
                        $className::mapping(),
                    ),
                ),
            ],
        ];

        return $schema;
    }

    public function setSchemaFactory(SchemaFactoryInterface $schemaFactory): void
    {
        if (! ($this->schemaFactory instanceof SchemaFactoryAwareInterface)) {
            return;
        }

        $this->schemaFactory->setSchemaFactory($schemaFactory);
    }
}
