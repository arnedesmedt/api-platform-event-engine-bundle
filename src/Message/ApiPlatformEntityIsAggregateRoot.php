<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

trait ApiPlatformEntityIsAggregateRoot
{
    /**
     * If null is returned the aggregate root linked with the command will be choosen.
     *
     * @see src/Config.php:L71
     *
     * @inheritDoc
     */
    public static function entity() : ?string
    {
        return null;
    }
}
