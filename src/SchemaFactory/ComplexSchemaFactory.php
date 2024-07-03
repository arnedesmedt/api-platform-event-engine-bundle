<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Documentation\ComplexTypeExtractor;
use ADS\JsonImmutableObjects\Polymorphism\Discriminator;
use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryAwareInterface;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Util\ResourceClassInfoTrait;
use ArrayObject;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

use function sprintf;

#[AsDecorator('api_platform.json_schema.schema_factory', priority: 5)]
final class ComplexSchemaFactory implements SchemaFactoryInterface, SchemaFactoryAwareInterface
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
     * @param class-string<JsonSchemaAwareRecord|Discriminator> $className
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
        $schema ??= new Schema(Schema::VERSION_OPENAPI);

        if (! ComplexTypeExtractor::isClassComplexType($className)) {
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

        if ((new ReflectionClass($className))->implementsInterface(JsonSchemaAwareRecord::class)) {
            $complexSchema = new Schema();

            $complexSchema['type'] = $forceCollection
                ? 'array'
                : ComplexTypeExtractor::complexType($className);

            if ($forceCollection) {
                $complexSchema['items'] = ['type' => ComplexTypeExtractor::complexType($className)];
            }

            $schema->getDefinitions()[$className::__type()] = new ArrayObject($complexSchema->getArrayCopy());
            $schema['$ref'] = sprintf(
                $schema->getVersion() === Schema::VERSION_OPENAPI
                    ? '#/components/schemas/%s'
                    : '#/definitions/%s',
                $className::__type(),
            );

            return $schema;
        }

        $schema['type'] = $forceCollection
            ? 'array'
            : ComplexTypeExtractor::complexType($className);

        if ($forceCollection) {
            $schema['items'] = ['type' => ComplexTypeExtractor::complexType($className)];
        }

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
