<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Callback\ImmutableObject\CallbackRequestBody;
use ADS\Bundle\ApiPlatformEventEngineBundle\Responses\Accepted;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use Symfony\Component\HttpFoundation\Response;

trait CallbackMessageLogic
{
    use SimpleCallbackMessageLogic;

    public static function __defaultCallbackEvent(): string
    {
        return 'success';
    }

    /** @inheritDoc */
    public static function __callbackEvents(): array
    {
        return [
            self::__defaultCallbackEvent() => CallbackRequestBody::class,
        ];
    }

    /** @inheritDoc */
    public static function __callbackEvent(array $callbackResponses): string
    {
        return self::__defaultCallbackEvent();
    }

    /** @inheritDoc */
    public static function __callbackRequestBody(string $callbackEvent, array $callbackResponses): array
    {
        return ['event' => $callbackEvent];
    }

    /** @inheritDoc */
    public static function __callbackMessagesPayloadGenerator(array $callbackResponses): array
    {
        return [];
    }

    /** @return array<int, class-string<JsonSchemaAwareRecord>> */
    public static function __extraResponseClassesCallback(): array
    {
        return [
            Response::HTTP_ACCEPTED => Accepted::class,
        ];
    }
}
