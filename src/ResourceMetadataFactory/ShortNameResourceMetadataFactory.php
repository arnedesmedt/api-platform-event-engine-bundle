<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataFactory;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use function array_filter;
use function array_map;
use function array_search;
use function explode;

final class ShortNameResourceMetadataFactory implements ResourceMetadataFactoryInterface
{
    public const PREFIX_NAMES = [
        'Entity',
        'Model',
    ];

    private ResourceMetadataFactoryInterface $decorated;

    public function __construct(ResourceMetadataFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    /**
     * @param class-string $resourceClass
     */
    public function create(string $resourceClass) : ResourceMetadata
    {
        $resourceMetadata = $this->decorated->create($resourceClass);

        if ($resourceMetadata->getShortName() !== 'State') {
            return $resourceMetadata;
        }

        // First try to find the short name by the class
        // Classes like *\Entity\Bank\* or *\Model\Bank\* will result in short name: Bank.
        $parts = explode('\\', $resourceClass);
        $positions = array_filter(
            array_map(
                static function (string $prefix) use ($parts) {
                    $position = array_search($prefix, $parts);

                    if ($position === false) {
                        return null;
                    }

                    return $position;
                },
                self::PREFIX_NAMES
            )
        );

        if (! empty($positions)) {
            foreach ($positions as $position) {
                $shortName = $parts[$position + 1] ?? null;

                if ($shortName) {
                    return $resourceMetadata->withShortName($shortName);
                }
            }
        }

        return $resourceMetadata;
    }
}
