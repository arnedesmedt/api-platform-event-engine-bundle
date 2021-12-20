<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Exception;

use ADS\JsonImmutableObjects\JsonSchemaAwareRecordLogic;
use EventEngine\JsonSchema\AnnotatedType;
use EventEngine\JsonSchema\JsonSchema;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use EventEngine\Schema\TypeSchema;

final class ApiPlatformException implements JsonSchemaAwareRecord
{
    use JsonSchemaAwareRecordLogic;

    public const REF = 'Exception';

    // phpcs:disable SlevomatCodingStandard.Classes.UnusedPrivateElements.UnusedProperty
    private readonly string $title; /** @phpstan-ignore-line */
    private readonly string $description; /** @phpstan-ignore-line */
    /** @phpstan-ignore-next-line */
    private readonly string $message;

    public static function typeRef(): TypeSchema
    {
        return JsonSchema::typeRef(self::REF);
    }

    public static function conflict(): TypeSchema
    {
        return self::schemaWithDescription('Conflict');
    }

    public static function notFound(): TypeSchema
    {
        return self::schemaWithDescription('Not found');
    }

    public static function badRequest(): TypeSchema
    {
        return self::schemaWithDescription('Bad request');
    }

    public static function unauthorized(): TypeSchema
    {
        return self::schemaWithDescription('Unauthorized');
    }

    public static function forbidden(): TypeSchema
    {
        return self::schemaWithDescription('Forbidden');
    }

    private static function schemaWithDescription(string $description): TypeSchema
    {
        $type = self::__schema();

        if (! $type instanceof AnnotatedType) {
            return $type;
        }

        return $type->describedAs($description);
    }
}
