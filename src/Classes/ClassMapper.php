<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Classes;

use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use ReflectionClass;

use function str_starts_with;

class ClassMapper
{
    /** @var array<class-string<JsonSchemaAwareRecord>, array<class-string<JsonSchemaAwareRecord>>>|null  */
    private array|null $resourceMessageMapping = null;
    /** @var array<class-string<JsonSchemaAwareRecord>, class-string<JsonSchemaAwareRecord>>|null  */
    private array|null $messageResourceMapping = null;

    /**
     * @param array<class-string<JsonSchemaAwareRecord>> $eventEngineResources
     * @param array<class-string<JsonSchemaAwareRecord>> $apiPlatformMessages
     */
    public function __construct(
        private readonly array $eventEngineResources,
        private readonly array $apiPlatformMessages,
    ) {
    }

    /** @return array<class-string<JsonSchemaAwareRecord>, array<class-string<JsonSchemaAwareRecord>>>  */
    public function resourceMessageMapping(): array
    {
        if ($this->resourceMessageMapping !== null) {
            return $this->resourceMessageMapping;
        }

        $this->mapResourcesToMessages();

        assert($this->resourceMessageMapping !== null);

        return $this->resourceMessageMapping;
    }

    /** @return array<class-string<JsonSchemaAwareRecord>, class-string<JsonSchemaAwareRecord>> */
    public function messageResourceMapping(): array
    {
        if ($this->messageResourceMapping !== null) {
            return $this->messageResourceMapping;
        }

        $this->mapResourcesToMessages();

        assert($this->resourceMessageMapping !== null);

        return $this->messageResourceMapping;
    }

    private function mapResourcesToMessages(): void
    {
        foreach ($this->eventEngineResources as $eventEngineResource) {
            $eventEngineResourceNamespace = (new ReflectionClass($eventEngineResource))->getNamespaceName();
            foreach ($this->apiPlatformMessages as $apiPlatformMessage) {
                if (! str_starts_with($eventEngineResourceNamespace, $apiPlatformMessage)) {
                    continue;
                }

                if (! isset($this->resourceMessageMapping[$eventEngineResource])) {
                    $this->resourceMessageMapping[$eventEngineResource] = [];
                }

                $this->resourceMessageMapping[$eventEngineResource][] = $apiPlatformMessage;
                $this->messageResourceMapping[$apiPlatformMessage] = $eventEngineResource;
            }
        }
    }
}
