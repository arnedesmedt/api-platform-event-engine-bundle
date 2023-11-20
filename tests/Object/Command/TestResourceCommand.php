<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Tests\Object\Command;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\DefaultApiPlatformMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\Tests\Object\Controller\TestResourceController;
use ADS\Bundle\EventEngineBundle\Attribute\ControllerCommand;
use ADS\JsonImmutableObjects\JsonSchemaAwareRecordLogic;

#[ControllerCommand(controller: TestResourceController::class)]
class TestResourceCommand implements ApiPlatformMessage
{
    use JsonSchemaAwareRecordLogic;
    use DefaultApiPlatformMessage;

    private string $test;

    public static function __uriTemplate(): string
    {
        return '/test';
    }
}
