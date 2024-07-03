<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Resource;

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
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

use function array_key_first;
use function assert;
use function is_string;
use function method_exists;
use function reset;
use function sprintf;
use function ucfirst;

#[AsDecorator('api_platform.api.identifiers_extractor')]
class IdentifierExtractor implements IdentifiersExtractorInterface
{
    use ResourceClassInfoTrait;

    public function __construct(
        #[AutowireDecorated]
        private readonly IdentifiersExtractorInterface $identifiersExtractor,
        #[Autowire('@api_platform.metadata.property.name_collection_factory')]
        private readonly PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory,
        #[Autowire('@api_platform.metadata.property.metadata_factory')]
        private readonly PropertyMetadataFactoryInterface $propertyMetadataFactory,
        #[Autowire('@api_platform.resource_class_resolver')]
        ResourceClassResolverInterface $resourceClassResolver,
        #[Autowire('@api_platform.metadata.resource.metadata_collection_factory')]
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
