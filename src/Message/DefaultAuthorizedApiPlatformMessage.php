<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

trait DefaultAuthorizedApiPlatformMessage
{
    use DefaultApiPlatformMessage;
    use DefaultAuthorizationMessage;
}
