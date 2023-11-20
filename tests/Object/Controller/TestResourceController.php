<?php

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Tests\Object\Controller;

use ADS\Bundle\ApiPlatformEventEngineBundle\Tests\Object\Command\TestResourceCommand;

class TestResourceController
{
    public function __invoke(TestResourceCommand $command): void
    {
        // TODO: Implement __invoke() method.
    }
}