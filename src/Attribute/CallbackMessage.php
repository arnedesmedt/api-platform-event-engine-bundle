<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Attribute;

use Attribute;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

#[Attribute(Attribute::TARGET_CLASS)]
class CallbackMessage
{
    public function __construct(

    ) {
    }
}
