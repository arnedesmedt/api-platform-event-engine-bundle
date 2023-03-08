<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Callback\ValueObject\CallbackUrl;

interface CallbackMessage
{
    public function callbackUrl(): CallbackUrl|null;

    public static function __defaultCallbackEvent(): string;

    /** @return array<string, string> */
    public static function __callbackEvents(): array;

    /** @param array<string, mixed> $callbackResponses */
    public static function __callbackEvent(array $callbackResponses): string;

    /**
     * @param array<string, mixed> $callbackResponses
     *
     * @return array<string, mixed>
     */
    public static function __callbackRequestBody(string $callbackEvent, array $callbackResponses): array;

    /**
     * @param array<string, mixed> $callbackResponses
     *
     * @return array<string, array<string, mixed>>
     */
    public static function __callbackMessagesPayloadGenerator(array $callbackResponses): array;
}
