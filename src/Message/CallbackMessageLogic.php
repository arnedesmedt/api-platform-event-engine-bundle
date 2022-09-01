<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Callback\ImmutableObject\CallbackRequestBody;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Callback\ValueObject\CallbackUrl;
use ADS\Bundle\ApiPlatformEventEngineBundle\Responses\Accepted;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use Symfony\Component\HttpFoundation\Response;

trait CallbackMessageLogic
{
    /**
     * The url that will be called if everything is executed.
     * If no 'callback_url' is provided, the callback call will not be executed.
     */
    private ?CallbackUrl $callbackUrl = null;

    public function callbackUrl(): ?CallbackUrl
    {
        return $this->callbackUrl;
    }

    public static function __defaultCallbackEvent(): string
    {
        return 'success';
    }

    /**
     * @inheritDoc
     */
    public static function __callbackEvents(): array
    {
        return [
            self::__defaultCallbackEvent() => CallbackRequestBody::class,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function __callbackEvent(array $callbackResponses): string
    {
        return self::__defaultCallbackEvent();
    }

    /**
     * @inheritDoc
     */
    public static function __callbackRequestBody(string $callbackEvent, array $callbackResponses): array
    {
        return ['event' => $callbackEvent];
    }

    /**
     * @inheritDoc
     */
    public static function __callbackMessagesPayloadGenerator(array $callbackResponses): array
    {
        return [];
    }

    /**
     * @return array<int, class-string<JsonSchemaAwareRecord>>
     */
    public static function __extraResponseClassesCallback(): array
    {
        return [
            Response::HTTP_ACCEPTED => Accepted::class,
        ];
    }
}
