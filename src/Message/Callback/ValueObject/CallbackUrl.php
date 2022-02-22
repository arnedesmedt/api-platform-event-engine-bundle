<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message\Callback\ValueObject;

use ADS\ValueObjects\HasExamples;
use ADS\ValueObjects\Implementation\ExamplesLogic;
use ADS\ValueObjects\Implementation\RulesLogic;
use ADS\ValueObjects\Implementation\String\StringValue;
use EventEngine\JsonSchema\ProvidesValidationRules;

use function sprintf;

final class CallbackUrl extends StringValue implements HasExamples, ProvidesValidationRules
{
    use ExamplesLogic;
    use RulesLogic;

    /**
     * @inheritDoc
     */
    public static function example()
    {
        return new static(
            sprintf(
                'http://webhook.site/%s',
                $_SERVER['WEBHOOK_UUID'] ?? 'get-your-uuid-from-webhook-site'
            )
        );
    }
}
