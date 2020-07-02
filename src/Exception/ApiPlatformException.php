<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Exception;

use EventEngine\JsonSchema\JsonSchema;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use EventEngine\JsonSchema\JsonSchemaAwareRecordLogic;
use EventEngine\Schema\TypeSchema;

final class ApiPlatformException implements JsonSchemaAwareRecord
{
    use JsonSchemaAwareRecordLogic;

    public const REF = 'Exception';

    // phpcs:disable SlevomatCodingStandard.Classes.UnusedPrivateElements.UnusedProperty
    private string $title;
    private string $description;
    private string $message;

    public static function typeRef(): TypeSchema
    {
        return JsonSchema::typeRef(self::REF);
    }
}
