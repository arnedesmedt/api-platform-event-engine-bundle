<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\EventEngineBundle\Response\DefaultResponses;

trait DefaultApiPlatformWithResponsesMessage
{
    use DefaultApiPlatformMessage;
    use DefaultResponses;
}
