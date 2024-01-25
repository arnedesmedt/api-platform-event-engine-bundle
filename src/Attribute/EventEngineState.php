<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Attribute;

use ApiPlatform\Metadata\ApiResource;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class EventEngineState extends ApiResource
{
}
