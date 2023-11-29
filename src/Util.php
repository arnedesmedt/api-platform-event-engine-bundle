<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle;

use ADS\Bundle\EventEngineBundle\Attribute\AggregateCommand;
use ADS\Bundle\EventEngineBundle\Attribute\ControllerCommand;
use ADS\Bundle\EventEngineBundle\Command\Command;
use ADS\Bundle\EventEngineBundle\Query\Query;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use ReflectionClass;

use function in_array;

final class Util
{
    /**
     * @param class-string<JsonSchemaAwareRecord> $messageClass
     * @param array<class-string> $messageInterfaces
     */
    public static function isQuery(string $messageClass, array $messageInterfaces): bool
    {
        if (in_array(Query::class, $messageInterfaces)) {
            return true;
        }

        $reflectionClass = new ReflectionClass($messageClass);

        $queryAttributes = $reflectionClass->getAttributes(\ADS\Bundle\EventEngineBundle\Attribute\Query::class);

        return ! empty($queryAttributes);
    }

    /**
     * @param class-string<JsonSchemaAwareRecord> $messageClass
     * @param array<class-string> $messageInterfaces
     */
    public static function isCommand(string $messageClass, array $messageInterfaces): bool
    {
        if (in_array(Command::class, $messageInterfaces)) {
            return true;
        }

        $reflectionClass = new ReflectionClass($messageClass);

        $controllerCommandAttributes = $reflectionClass->getAttributes(ControllerCommand::class);

        if (! empty($controllerCommandAttributes)) {
            return true;
        }

        $aggregateCommandAttributes = $reflectionClass->getAttributes(AggregateCommand::class);

        return ! empty($aggregateCommandAttributes);
    }
}
