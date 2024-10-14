<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message\Callback\ImmutableObject;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Callback\ValueObject\CallbackEvent;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Callback\ValueObject\CallbackMessage;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use TeamBlue\JsonImmutableObjects\JsonSchemaAwareRecordLogic;

class CallbackRequestBody implements JsonSchemaAwareRecord
{
    use JsonSchemaAwareRecordLogic;

    private CallbackEvent $event;
    private CallbackMessage|null $message = null;
}
