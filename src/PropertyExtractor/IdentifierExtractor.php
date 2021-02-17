<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyExtractor;

use ApiPlatform\Core\Api\IdentifiersExtractorInterface;

class IdentifierExtractor implements IdentifiersExtractorInterface
{
    private IdentifiersExtractorInterface $identifiersExtractor;

    public function __construct(IdentifiersExtractorInterface $identifiersExtractor)
    {
        $this->identifiersExtractor = $identifiersExtractor;
    }

    /**
     * @return array<mixed>
     *
     * @inheritDoc
     */
    public function getIdentifiersFromResourceClass(string $resourceClass): array
    {
        return $this->identifiersExtractor->getIdentifiersFromResourceClass($resourceClass);
    }

    /**
     * @return array<mixed>
     *
     * @inheritDoc
     */
    public function getIdentifiersFromItem($item): array
    {
        return $this->identifiersExtractor->getIdentifiersFromItem($item);
    }
}
