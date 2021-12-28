<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataFactory;

use ADS\Util\StringUtil;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;

final class ShortNameResourceMetadataFactory implements ResourceMetadataFactoryInterface
{
    public function __construct(private ResourceMetadataFactoryInterface $decorated)
    {
    }

    /**
     * @param class-string $resourceClass
     */
    public function create(string $resourceClass): ResourceMetadata
    {
        $resourceMetadata = $this->decorated->create($resourceClass);

        if ($resourceMetadata->getShortName() !== 'State') {
            return $resourceMetadata;
        }

        return $resourceMetadata->withShortName(StringUtil::entityNameFromClassName($resourceClass));
    }
}
