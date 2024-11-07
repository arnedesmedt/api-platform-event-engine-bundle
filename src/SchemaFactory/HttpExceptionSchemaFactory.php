<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\SchemaFactory;

use ADS\Bundle\EventEngineBundle\Exception\ExceptionExtractor;
use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryAwareInterface;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Util\ResourceClassInfoTrait;
use ArrayObject;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use TeamBlue\JsonImmutableObjects\Polymorphism\Discriminator;

#[AsDecorator('api_platform.json_schema.schema_factory')]
class HttpExceptionSchemaFactory implements SchemaFactoryInterface, SchemaFactoryAwareInterface
{
    use ResourceClassInfoTrait;

    public function __construct(
        #[AutowireDecorated]
        private SchemaFactoryInterface $schemaFactory,
        private readonly ExceptionExtractor $exceptionExtractor,
    ) {
        if (! ($this->schemaFactory instanceof SchemaFactoryAwareInterface)) {
            return;
        }

        $this->schemaFactory->setSchemaFactory($this);
    }

    /**
     * @param class-string<JsonSchemaAwareRecord|Discriminator> $className
     * @param array<string, mixed>|null                         $serializerContext
     * @param Schema<mixed>|null                                $schema
     *
     * @return Schema<mixed>
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
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
        $schema = $this->schemaFactory->buildSchema(
            $className,
            $format,
            $type,
            $operation,
            $schema,
            $serializerContext,
            $forceCollection,
        );

        $messageClass = $operation?->getInput()['class'] ?? null;

        if ($messageClass === null || $type !== Schema::TYPE_OUTPUT) {
            return $schema;
        }

        $definitions = $schema->getDefinitions();

        foreach ($this->exceptionExtractor->extract($messageClass) as $exceptionClass) {
            $schemaName = $exceptionClass::__type();

            if (isset($definitions[$schemaName])) {
                continue;
            }

            $definitions[$schemaName] = new ArrayObject($exceptionClass::__schema()->toArray());
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
