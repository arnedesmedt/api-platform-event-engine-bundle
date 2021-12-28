<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyExtractor;

use ADS\Bundle\ApiPlatformEventEngineBundle\Resource\ChangeIdentifierResource;
use ADS\Util\ArrayUtil;
use ADS\ValueObjects\ValueObject;
use ApiPlatform\Core\Api\IdentifiersExtractorInterface;
use ReflectionClass;

use function array_map;

class IdentifierExtractor implements IdentifiersExtractorInterface
{
    public function __construct(private IdentifiersExtractorInterface $identifiersExtractor)
    {
    }

    /**
     * @param class-string $resourceClass
     *
     * @return array<mixed>
     *
     * @inheritDoc
     */
    public function getIdentifiersFromResourceClass(string $resourceClass): array
    {
        $identifiers = $this->identifiersExtractor->getIdentifiersFromResourceClass($resourceClass);

        $identifiers = $this->changeListOfIdentifiers($identifiers, $resourceClass);

        return $this->snakeCasedIdentifiers($identifiers);
    }

    /**
     * @return array<mixed>
     *
     * @inheritDoc
     */
    public function getIdentifiersFromItem($item): array
    {
        $identifiers = $this->identifiersExtractor->getIdentifiersFromItem($item);

        $identifiers = $this->changeMapOfIdentifiers($identifiers, $item);
        $identifiers = $this->serializeValueObjects($identifiers);

        return $this->snakeCasedIdentifierKeys($identifiers);
    }

    /**
     * @param array<int, string> $identifiers
     * @param class-string $resourceClass
     *
     * @return array<int, string>
     */
    private function changeListOfIdentifiers(array $identifiers, string $resourceClass): array
    {
        $reflectionClass = new ReflectionClass($resourceClass);

        if (! $reflectionClass->implementsInterface(ChangeIdentifierResource::class)) {
            return $identifiers;
        }

        $identifierNameMapping = $resourceClass::identifierNameMapping();

        foreach ($identifiers as &$identifier) {
            $identifier = $identifierNameMapping[$identifier] ?? $identifier;
        }

        return $identifiers;
    }

    /**
     * @param array<string, mixed> $identifiers
     *
     * @return array<string, mixed>
     */
    private function changeMapOfIdentifiers(array $identifiers, mixed $resource): array
    {
        if (! $resource instanceof ChangeIdentifierResource) {
            return $identifiers;
        }

        $identifierNameMapping = $resource::identifierNameMapping();

        foreach ($identifierNameMapping as $oldIdentifierName => $newIdentifierName) {
            if (! isset($identifiers[$oldIdentifierName])) {
                continue;
            }

            $identifiers[$newIdentifierName] = $identifiers[$oldIdentifierName];
            unset($identifiers[$oldIdentifierName]);
        }

        return $identifiers;
    }

    /**
     * @param array<string, mixed> $identifiers
     *
     * @return array<string, mixed>
     */
    private function serializeValueObjects(array $identifiers): array
    {
        return array_map(
            static fn ($identifier) => $identifier instanceof ValueObject ? $identifier->toValue() : $identifier,
            $identifiers
        );
    }

    /**
     * @param array<string, mixed> $identifiers
     *
     * @return array<mixed>
     */
    private function snakeCasedIdentifierKeys(array $identifiers): array
    {
        return ArrayUtil::toSnakeCasedKeys($identifiers);
    }

    /**
     * @param array<int, string> $identifiers
     *
     * @return array<int, string>
     */
    private function snakeCasedIdentifiers(array $identifiers): array
    {
        /** @var array<int, string> $result */
        $result = ArrayUtil::toSnakeCasedValues($identifiers);

        return $result;
    }
}
