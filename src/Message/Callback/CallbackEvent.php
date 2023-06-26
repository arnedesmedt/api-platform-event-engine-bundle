<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message\Callback;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Callback\ImmutableObject\CallbackRequestBody;
use ADS\Bundle\EventEngineBundle\Event\Event;

interface CallbackEvent extends Event
{
    public function callbackRequestBody(): CallbackRequestBody;
}
