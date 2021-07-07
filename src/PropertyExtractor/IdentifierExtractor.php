<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyExtractor;

use ADS\Util\ArrayUtil;
use ADS\ValueObjects\ValueObject;
use ApiPlatform\Core\Api\IdentifiersExtractorInterface;

use function array_map;

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
        $identifiers = $this->identifiersExtractor->getIdentifiersFromResourceClass($resourceClass);

        return ArrayUtil::toSnakeCasedValues($identifiers);
    }

    /**
     * @return array<mixed>
     *
     * @inheritDoc
     */
    public function getIdentifiersFromItem($item): array
    {
        $identifiers = $this->identifiersExtractor->getIdentifiersFromItem($item);

        $identifiers = array_map(
            static fn ($identifier) => $identifier instanceof ValueObject ? $identifier->toValue() : $identifier,
            $identifiers
        );

        return ArrayUtil::toSnakeCasedValues($identifiers);
    }
}
