<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyAccessor;

use EventEngine\Data\ImmutableRecord;
use EventEngine\Data\SpecialKeySupport;
use RuntimeException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyAccess\PropertyPathInterface;

use function array_search;
use function assert;
use function is_string;
use function method_exists;
use function sprintf;

class ImmutableRecordPropertyAccessor implements PropertyAccessorInterface
{
    /** @param object|array<string, mixed> $objectOrArray */
    public function setValue(
        object|array &$objectOrArray,
        PropertyPathInterface|string $propertyPath,
        mixed $value,
    ): void {
        // TODO: Implement setValue() method.
    }

    /** @param object|array<string, mixed> $objectOrArray */
    public function getValue(object|array $objectOrArray, PropertyPathInterface|string $propertyPath): mixed
    {
        assert($objectOrArray instanceof ImmutableRecord);

        if ($objectOrArray instanceof SpecialKeySupport && method_exists($objectOrArray, 'keyMapping')) {
            $keyMapping = $objectOrArray->keyMapping();
            $newPropertyPath = array_search($propertyPath, $keyMapping, true);

            if ($newPropertyPath) {
                $propertyPath = $newPropertyPath;
            }
        }

        $data = $objectOrArray->toArray();

        if (is_string($propertyPath)) {
            if (method_exists($objectOrArray, $propertyPath)) {
                return $objectOrArray->$propertyPath();
            }

            return $data[$propertyPath];
        }

        // todo implement propertyPathInterface
        throw new RuntimeException(
            sprintf('%s doesn\'t support \'%s\'.', self::class, PropertyPathInterface::class),
        );
    }

    /** @param object|array<string, mixed> $objectOrArray */
    public function isWritable(object|array $objectOrArray, PropertyPathInterface|string $propertyPath): bool
    {
        return true;
        // TODO: Implement isWritable() method.
    }

    /** @param object|array<string, mixed> $objectOrArray */
    public function isReadable(object|array $objectOrArray, PropertyPathInterface|string $propertyPath): bool
    {
        return true;
        // TODO: Implement isReadable() method.
    }
}
