<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use TeamBlue\Util\StringUtil;

use function sprintf;

trait DefaultApiPlatformMessageWithStateResource
{
    use DefaultApiPlatformMessage;

    /** @return class-string */
    public static function __resource(): string
    {
        $resourceNamespace = StringUtil::entityNamespaceFromClassName(static::class);
        $resourceName = StringUtil::entityNameFromClassName(static::class);

        /** @var class-string $stateClass */
        $stateClass = sprintf('%s\\%s\\State', $resourceNamespace, $resourceName);

        return $stateClass;
    }
}
