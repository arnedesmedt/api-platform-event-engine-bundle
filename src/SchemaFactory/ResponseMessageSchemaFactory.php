<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory;

use ADS\Bundle\EventEngineBundle\MetadataExtractor\ResponseExtractor;
use ADS\JsonImmutableObjects\Polymorphism\Discriminator;
use ADS\ValueObjects\ListValue;
use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryAwareInterface;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Util\ResourceClassInfoTrait;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

use function class_implements;
use function in_array;

#[AsDecorator('api_platform.json_schema.schema_factory', priority: 1)]
final class ResponseMessageSchemaFactory implements SchemaFactoryInterface, SchemaFactoryAwareInterface
{
    use ResourceClassInfoTrait;

    public function __construct(
        #[AutowireDecorated]
        private SchemaFactoryInterface $schemaFactory,
        private readonly ResponseExtractor $responseExtractor,
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

        $input = $operation?->getInput();
        $messageClass = $input['class'] ?? null;
        $reflectionClass = $messageClass ? new ReflectionClass($messageClass) : null;

        if (
            $type === Schema::TYPE_OUTPUT
            && $reflectionClass !== null
            && $this->responseExtractor->hasResponsesFromReflectionClass($reflectionClass)
        ) {
            $className = $this->responseExtractor->defaultResponseClassFromReflectionClass($reflectionClass);

            if (in_array(ListValue::class, class_implements($className) ?: [])) {
                // @phpstan-ignore-next-line
                $className = $className::itemType();
                $forceCollection = true;
            }

            $serializerContext = null;
        }

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

    public function setSchemaFactory(SchemaFactoryInterface $schemaFactory): void
    {
        if (! ($this->schemaFactory instanceof SchemaFactoryAwareInterface)) {
            return;
        }

        $this->schemaFactory->setSchemaFactory($schemaFactory);
    }
}
