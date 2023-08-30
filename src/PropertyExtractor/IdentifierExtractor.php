<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyExtractor;

use ADS\ValueObjects\ValueObject;
use ApiPlatform\Api\IdentifiersExtractorInterface;
use ApiPlatform\Api\ResourceClassResolverInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Util\ResourceClassInfoTrait;
use EventEngine\Data\ImmutableRecord;
use RuntimeException;

use function array_key_first;
use function assert;
use function is_string;
use function method_exists;
use function reset;
use function sprintf;
use function ucfirst;

class IdentifierExtractor implements IdentifiersExtractorInterface
{
    use ResourceClassInfoTrait;

    public function __construct(
        private readonly IdentifiersExtractorInterface $identifiersExtractor,
        private readonly PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory,
        private readonly PropertyMetadataFactoryInterface $propertyMetadataFactory,
        ResourceClassResolverInterface $resourceClassResolver,
        ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
    ) {
        $this->resourceClassResolver = $resourceClassResolver;
        $this->resourceMetadataFactory = $resourceMetadataCollectionFactory;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<mixed>
     *
     * @inheritDoc
     */
    public function getIdentifiersFromItem(object $item, Operation|null $operation = null, array $context = []): array
    {
        $resourceClass = $this->getResourceClass($item);

        if (! $item instanceof ImmutableRecord || ! $resourceClass) {
            return $this->identifiersExtractor->getIdentifiersFromItem($item, $operation, $context);
        }

        $identifiers = [];
        /** @var string $propertyName */
        foreach ($this->propertyNameCollectionFactory->create($resourceClass) as $propertyName) {
            $propertyMetadata = $this->propertyMetadataFactory->create($resourceClass, $propertyName);

            if (! $propertyMetadata->isIdentifier()) {
                continue;
            }

            if (! method_exists($item, $propertyName)) {
                throw new RuntimeException(
                    sprintf('Can\'t find method \'%s\' to access data in object \'%s\'.', $propertyName, $item::class),
                );
            }

            $value = $item->$propertyName();

            if ($value instanceof ImmutableRecord) {
                $valueIdentifiers = $this->getIdentifiersFromItem($value);
                $valueFirstIdentifier = reset($valueIdentifiers);

                if ($valueFirstIdentifier === false) {
                    throw new RuntimeException(sprintf('No identifiers found for \'%s\'', $value::class));
                }

                $value = $valueFirstIdentifier;
                $firstKey = array_key_first($valueIdentifiers);
                assert(is_string($firstKey));
                $propertyName .= ucfirst($firstKey);
            }

            if ($value instanceof ValueObject) {
                $value = $value->toValue();
            }

            $identifiers[$propertyName] = $value;
        }

        return $identifiers;
    }
}
