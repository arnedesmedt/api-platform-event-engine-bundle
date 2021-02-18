<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use EventEngine\JsonSchema\JsonSchemaAwareRecord;

interface ApiPlatformMessage extends JsonSchemaAwareRecord
{
    /**
     * @return class-string
     */
    public static function __entity(): string;

    public static function __operationType(): string;

    public static function __operationName(): string;

    public static function __operationId(): string;

    public static function __httpMethod(): ?string;

    public static function __path(): ?string;

    public static function __apiPlatformController(): string;

    public static function __stateless(): ?bool;

    /**
     * @return array<string>
     */
    public static function __tags(): array;

    /**
     * If the request body is an array, we use a property that will used for the request body
     */
    public static function __requestBodyArrayProperty(): ?string;
}
