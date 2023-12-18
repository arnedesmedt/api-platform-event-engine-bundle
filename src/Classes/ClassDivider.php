<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Classes;

use ADS\Bundle\ApiPlatformEventEngineBundle\Attribute\ApiPlatformMessage as ApiPlatformMessageAttribute;
use ADS\Bundle\ApiPlatformEventEngineBundle\Attribute\EventEngineResource;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ApiPlatform\Metadata\Util\ReflectionClassRecursiveIterator;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use Iterator;
use ReflectionAttribute;
use ReflectionClass;
use RuntimeException;

use function array_keys;
use function sprintf;

// phpcs:disable SlevomatCodingStandard.Commenting.InlineDocCommentDeclaration.MissingVariable
class ClassDivider
{
    /** @var Iterator<class-string, ReflectionClass<object>> */
    private readonly Iterator $reflectionClasses;
    /** @var array<class-string<JsonSchemaAwareRecord>, ReflectionClass<JsonSchemaAwareRecord>> */
    private array $eventEngineResources;
    /** @var array<class-string<JsonSchemaAwareRecord>, ReflectionClass<JsonSchemaAwareRecord>> */
    private array $apiPlatformMessages;

    /** @param array<string> $directories */
    public function __construct(readonly array $directories)
    {
        if (empty($directories)) {
            throw new RuntimeException(
                sprintf(
                    'No directories configured for %s',
                    self::class,
                ),
            );
        }

        $this->reflectionClasses = ReflectionClassRecursiveIterator::getReflectionClassesFromDirectories($directories);
    }

    private function init(): void
    {
        $this->eventEngineResources = [];

        $this->reflectionClasses->rewind();

        foreach ($this->reflectionClasses as $className => $reflectionClass) {
            $this->addPossibleEventEngineResource($className, $reflectionClass)
            || $this->addPossibleApiPlatformMessage($className, $reflectionClass);
        }
    }

    /**
     * @param class-string $className
     * @param ReflectionClass<object> $reflectionClass
     */
    private function addPossibleEventEngineResource(string $className, ReflectionClass $reflectionClass): bool
    {
        $isEventEngineResource = $reflectionClass->getAttributes(
            EventEngineResource::class,
            ReflectionAttribute::IS_INSTANCEOF,
        ) && ! $reflectionClass->isAbstract();

        if ($isEventEngineResource) {
            $this->isJsonSchemaAwareRecord($className, $reflectionClass, 'event engine resource');
            /** @var class-string<JsonSchemaAwareRecord> $className */
            /** @var ReflectionClass<JsonSchemaAwareRecord> $reflectionClass */
            $this->eventEngineResources[$className] = $reflectionClass;
        }

        return $isEventEngineResource;
    }

    /**
     * @param class-string $className
     * @param ReflectionClass<object> $reflectionClass
     */
    private function addPossibleApiPlatformMessage(string $className, ReflectionClass $reflectionClass): bool
    {
        $isApiPlatformMessage = $reflectionClass->getAttributes(
            ApiPlatformMessageAttribute::class,
            ReflectionAttribute::IS_INSTANCEOF,
        ) || $reflectionClass->implementsInterface(ApiPlatformMessage::class)
            && ! $reflectionClass->isAbstract();

        if ($isApiPlatformMessage) {
            $this->isJsonSchemaAwareRecord($className, $reflectionClass, 'api platform message');
            /** @var class-string<JsonSchemaAwareRecord> $className */
            /** @var ReflectionClass<JsonSchemaAwareRecord> $reflectionClass */
            $this->apiPlatformMessages[$className] = $reflectionClass;
        }

        return $isApiPlatformMessage;
    }

    /**
     * @param class-string $className
     * @param ReflectionClass<object> $reflectionClass
     */
    private function isJsonSchemaAwareRecord(string $className, ReflectionClass $reflectionClass, string $type): void
    {
        if ($reflectionClass->implementsInterface(JsonSchemaAwareRecord::class)) {
            return;
        }

        throw new RuntimeException(
            sprintf(
                'Class %s is not a JsonSchemaAwareRecord, but should be used as %s',
                $className,
                $type,
            ),
        );
    }

    /** @return array<class-string<JsonSchemaAwareRecord>, ReflectionClass<JsonSchemaAwareRecord>> */
    public function eventEngineResources(): array
    {
        if (! isset($this->eventEngineResources)) {
            $this->init();
        }

        return $this->eventEngineResources;
    }

    /** @return array<class-string<JsonSchemaAwareRecord>> */
    public function eventEngineResourceClasses(): array
    {
        return array_keys($this->eventEngineResources());
    }

    /** @return array<class-string<JsonSchemaAwareRecord>, ReflectionClass<JsonSchemaAwareRecord>> */
    public function apiPlatformMessages(): array
    {
        if (! isset($this->apiPlatformMessages)) {
            $this->init();
        }

        return $this->apiPlatformMessages;
    }

    /** @return array<class-string<JsonSchemaAwareRecord>> */
    public function apiPlatformMessageClasses(): array
    {
        return array_keys($this->apiPlatformMessages());
    }
}
