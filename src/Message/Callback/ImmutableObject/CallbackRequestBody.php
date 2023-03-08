<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message\Callback\ImmutableObject;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Callback\ValueObject\CallbackEvent;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Callback\ValueObject\CallbackMessage;
use ADS\JsonImmutableObjects\JsonSchemaAwareRecordLogic;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;

class CallbackRequestBody implements JsonSchemaAwareRecord
{
    use JsonSchemaAwareRecordLogic;

    private CallbackEvent $event;
    private CallbackMessage|null $message = null;
}
