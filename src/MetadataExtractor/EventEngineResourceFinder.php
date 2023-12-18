<?php

namespace ADS\Bundle\ApiPlatformEventEngineBundle\MetadataExtractor;


use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use LogicException;
use ReflectionClass;

class EventEngineResourceFinder
{
    /** @param array<class-string<JsonSchemaAwareRecord>> $eventEngineResources */
    public function __construct(
        private readonly array $eventEngineResources,
    ) {
    }

    public function withMatchingNamespace(ReflectionClass $reflectionClass): string
    {
        foreach($this->eventEngineResources as $eventEngineResourceClass) {
            $eventEngineResourceNamespace = (new ReflectionClass($eventEngineResourceClass))->getNamespaceName();
            if (str_starts_with($eventEngineResourceNamespace, $reflectionClass->getName())) {
                return $eventEngineResourceClass;
            }
        }

        throw new LogicException(
            sprintf(
                'No matching event engine resource found for \'%s\'.',
                $reflectionClass->getName(),
            ),
        );
    }

}