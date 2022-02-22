<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Callback\ValueObject\CallbackUrl;

interface CallbackMessage
{
    public function callbackUrl(): ?CallbackUrl;

    public function __defaultCallbackEvent(): string;

    /**
     * @return array<string, string>
     */
    public function __callbackEvents(): array;

    /**
     * @param array<string, mixed> $callbackResponses
     */
    public function __callbackEvent(array $callbackResponses): string;

    /**
     * @param array<string, mixed> $callbackResponses
     *
     * @return array<string, mixed>
     */
    public function __callbackRequestBody(string $callbackEvent, array $callbackResponses): array;
}
