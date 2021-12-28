<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Documentation;

use ADS\Util\StringUtil;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use RuntimeException;

use function array_map;
use function array_search;
use function in_array;
use function iterator_to_array;
use function sprintf;
use function usort;

/**
 * @method string[] tagOrder()
 */
trait ResourcesAreTags
{
    /** @readonly */
    private ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory;
    /** @readonly */
    private ResourceMetadataFactoryInterface $resourceMetadataFactory;

    public function __construct(
        ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory,
        ResourceMetadataFactoryInterface $resourceMetadataFactory
    ) {
        $this->resourceNameCollectionFactory = $resourceNameCollectionFactory;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
    }

    /**
     * @inheritDoc
     */
    public function tags(): array
    {
        $tags = array_map(
            function ($resourceClass) {
                $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);
                $shortName = StringUtil::entityNameFromClassName($resourceClass);

                if (! in_array($shortName, $this->tagOrder())) {
                    throw new RuntimeException(
                        sprintf(
                            'Resource name \'%s\' not found as a tag in \'%s\'.',
                            $shortName,
                            static::class
                        )
                    );
                }

                return new Tag(
                    $shortName,
                    $resourceMetadata->getDescription()
                );
            },
            iterator_to_array($this->resourceNameCollectionFactory->create()->getIterator())
        );

        usort(
            $tags,
            function (Tag $tagA, Tag $tagB) {
                $indexA = array_search($tagA->getName(), $this->tagOrder());
                $indexB = array_search($tagB->getName(), $this->tagOrder());

                return (int) $indexA - (int) $indexB;
            }
        );

        return $tags;
    }
}
