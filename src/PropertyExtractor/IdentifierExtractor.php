<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyExtractor;

use ApiPlatform\Api\IdentifiersExtractorInterface;
use ApiPlatform\Metadata\Operation;

class IdentifierExtractor implements IdentifiersExtractorInterface
{
    public function __construct(private IdentifiersExtractorInterface $identifiersExtractor)
    {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<mixed>
     *
     * @inheritDoc
     */
    public function getIdentifiersFromItem($item, ?Operation $operation = null, array $context = []): array
    {
        return $this->identifiersExtractor->getIdentifiersFromItem($item, $operation, $context);
    }
}
