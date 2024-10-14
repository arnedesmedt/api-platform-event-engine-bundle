<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Exception;

use ADS\Bundle\EventEngineBundle\Type\AnnotatedTypeRef;
use ADS\Bundle\EventEngineBundle\Type\Type;
use EventEngine\JsonSchema\AnnotatedType;
use EventEngine\JsonSchema\JsonSchema;
use EventEngine\Schema\TypeSchema;
use TeamBlue\JsonImmutableObjects\JsonSchemaAwareRecordLogic;

class ApiPlatformException implements Type
{
    use JsonSchemaAwareRecordLogic;

    public const REF = 'ApiPlatformException';

    // phpcs:disable SlevomatCodingStandard.Classes.UnusedPrivateElements.UnusedProperty
    /** @readonly */
    protected string $type;
    /** @readonly */
    protected string $title;
    /** @readonly */
    protected string $detail;

    public static function typeRef(): TypeSchema
    {
        return AnnotatedTypeRef::fromTypeRef(JsonSchema::typeRef(self::REF));
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

    public static function unprocessableEntity(): TypeSchema
    {
        return self::schemaWithDescription('Unprocessable entity');
    }

    public static function schemaWithDescription(string $description): TypeSchema
    {
        $type = self::typeRef();

        if (! $type instanceof AnnotatedType) {
            return $type;
        }

        return $type->describedAs($description);
    }
}
