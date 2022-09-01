<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use EventEngine\JsonSchema\JsonSchemaAwareCollection;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;

interface ApiPlatformMessage extends JsonSchemaAwareRecord
{
    /**
     * @return class-string
     */
    public static function __resource(): string;

    public static function __isCollection(): bool;

    public static function __operationName(): string;

    public static function __operationId(): string;

    public static function __httpMethod(): string;

    public static function __uriTemplate(): string;

    public static function __apiPlatformController(): string;

    public static function __processor(): ?string;

    public static function __stateless(): ?bool;

    /**
     * @return class-string<JsonSchemaAwareRecord>
     */
    public static function __schemaStateClass(): string;

    /**
     * @return class-string<JsonSchemaAwareCollection>
     */
    public static function __schemaStatesClass(): ?string;

    /**
     * @return array<string>
     */
    public static function __tags(): array;

    /**
     * If the request body is an array, we use a property that will used for the request body
     */
    public static function __requestBodyArrayProperty(): ?string;

    public static function __inputClass(): ?string;

    public static function __outputClass(): ?string;

    /**
     * @return array<string, mixed>
     */
    public static function __normalizationContext(): array;

    /**
     * @return array<string, mixed>
     */
    public static function __denormalizationContext(): array;

    public static function __overrideDefaultApiPlatformResponse(): bool;
}
