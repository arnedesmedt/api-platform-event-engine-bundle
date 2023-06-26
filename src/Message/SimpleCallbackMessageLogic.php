<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Callback\ValueObject\CallbackUrl;

trait SimpleCallbackMessageLogic
{
    /**
     * The url that will be called if everything is executed.
     * If no 'callback_url' is provided, the callback call will not be executed.
     */
    private CallbackUrl|null $callbackUrl = null;

    public function callbackUrl(): CallbackUrl|null
    {
        return $this->callbackUrl;
    }
}
