<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Callback\ValueObject\CallbackUrl;

interface SimpleCallbackMessage
{
    public function callbackUrl(): CallbackUrl|null;
}
