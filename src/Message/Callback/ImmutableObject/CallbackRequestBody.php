<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message\Callback\ImmutableObject;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Callback\ValueObject\CallbackEvent;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Callback\ValueObject\CallbackMessage;
use ADS\JsonImmutableObjects\DefaultsAreNotRequired;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;

class CallbackRequestBody implements JsonSchemaAwareRecord
{
    use DefaultsAreNotRequired;

    private CallbackEvent $event;
    private ?CallbackMessage $message = null;
}
