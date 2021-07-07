<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Filter;

use ADS\Util\StringUtil;
use ApiPlatform\Core\Api\FilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\OrderFilterInterface;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use ReflectionClass;
use RuntimeException;

use function array_fill_keys;
use function array_key_exists;
use function array_keys;
use function sprintf;
use function strtolower;

class OrderFilter implements FilterInterface
{
    /** @var array<mixed>|null */
    protected ?array $properties = null;
    private string $orderParameterName;

    /**
     * @param array<mixed>|null $properties
     */
    public function __construct(string $orderParameterName = 'order', ?array $properties = null)
    {
        $this->orderParameterName = $orderParameterName;
        $this->properties = $properties;
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
                    'The event engine order filter can\'t be applied to a resource ' .
                    'if it\'s not implementing the \'%s\' interface.',
                    JsonSchemaAwareRecord::class
                )
            );
        }

        $schema = $resourceClass::__schema()->toArray();

        if ($this->properties === null) {
            $this->properties = array_fill_keys(array_keys($schema['properties'] ?? []), null);
        }

        foreach ($this->properties as $propertyName => $property) {
            if (! array_key_exists($propertyName, $schema['properties'] ?? [])) {
                continue;
            }

            $description[sprintf('%s[%s]', $this->orderParameterName, StringUtil::decamelize($propertyName))] = [
                'property' => $property,
                'type' => 'string',
                'required' => false,
                'schema' => [
                    'type' => 'string',
                    'enum' => [
                        strtolower(OrderFilterInterface::DIRECTION_ASC),
                        strtolower(OrderFilterInterface::DIRECTION_DESC),
                    ],
                ],
            ];
        }

        return $description;
    }
}
