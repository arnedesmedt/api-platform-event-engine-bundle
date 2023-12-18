<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Filter;

use ADS\Util\StringUtil;
use ApiPlatform\Api\FilterInterface;
use ApiPlatform\Doctrine\Common\Filter\SearchFilterInterface;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use ReflectionClass;
use RuntimeException;

use function array_key_exists;
use function explode;
use function sprintf;

class SearchFilter implements FilterInterface
{
    /** @param array<mixed> $properties */
    public function __construct(protected array $properties = [])
    {
    }

    /**
     * @param class-string $resourceClass
     *
     * @return array<mixed>
     *
     * @inheritDoc
     */
    public function getDescription(string $resourceClass): array
    {
        $description = [];

        $reflectionClass = new ReflectionClass($resourceClass);

        if (! $reflectionClass->implementsInterface(JsonSchemaAwareRecord::class)) {
            throw new RuntimeException(
                sprintf(
                    'The event engine search filter can\'t be applied to a resource ' .
                    'if it\'s not implementing the \'%s\' interface.',
                    JsonSchemaAwareRecord::class,
                ),
            );
        }

        $schema = $resourceClass::__schema()->toArray();

        foreach ($this->properties as $propertyName => $property) {
            $propertyNameParts = explode('.', $propertyName);

            foreach ($propertyNameParts as $propertyNamePart) {
                if (! array_key_exists($propertyNamePart, $schema['properties'] ?? [])) {
                    continue 2;
                }

                $schema = $schema['properties'][$propertyNamePart];
            }

            $camelizedPropertyName = StringUtil::camelize($propertyName, '.');

            $description[$camelizedPropertyName] = [
                'property' => $camelizedPropertyName,
                'type' => $schema['type'],
                'required' => false,
                'strategy' => SearchFilterInterface::STRATEGY_PARTIAL,
                'is_collection' => false,
            ];
        }

        return $description;
    }
}
