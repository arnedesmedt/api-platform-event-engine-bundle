<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle;

use ADS\Bundle\EventEngineBundle\Attribute\Command as CommandAttribute;
use ADS\Bundle\EventEngineBundle\Attribute\Query as QueryAttribute;
use ADS\Bundle\EventEngineBundle\Command\Command;
use ADS\Bundle\EventEngineBundle\Query\Query;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use ReflectionAttribute;
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

        $queryAttributes = $reflectionClass->getAttributes(
            QueryAttribute::class,
            ReflectionAttribute::IS_INSTANCEOF,
        );

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

        $commandAttributes = $reflectionClass->getAttributes(
            CommandAttribute::class,
            ReflectionAttribute::IS_INSTANCEOF,
        );

        return ! empty($commandAttributes);
    }
}
