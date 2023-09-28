<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\PropertyExtractor;

use InvalidArgumentException;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;
use Symfony\Component\PropertyInfo\PropertyDescriptionExtractorInterface;

use function reset;
use function sprintf;

class TypeDescriptionExtractor implements PropertyDescriptionExtractorInterface
{
    /**
     * @param class-string         $class
     * @param array<string, mixed> $context
     */
    public function getShortDescription(string $class, string $property, array $context = []): string|null
    {
        $docBlock = $this->docBlock($class, $property);

        return $docBlock?->getSummary();
    }

    /**
     * @param class-string         $class
     * @param array<string, mixed> $context
     */
    public function getLongDescription(string $class, string $property, array $context = []): string|null
    {
        $docBlock = $this->docBlock($class, $property);
        $description = $docBlock?->getDescription()->render();

        return sprintf(
            '%s%s',
            $docBlock?->getSummary() ?? '',
            ! empty($description) ? "\n" . $description : '',
        );
    }

    /** @param class-string $class */
    private function docBlock(string $class, string $property): DocBlock|null
    {
        $reflectionPropertyType = PropertySchemaStateExtractor::reflectionPropertyType($class, $property);
        $objectTypes = PropertySchemaStateExtractor::objectTypes($reflectionPropertyType);
        $firstObjectType = reset($objectTypes);

        if ($firstObjectType === false) {
            return null;
        }

        try {
            return DocBlockFactory::createInstance()->create($firstObjectType);
        } catch (InvalidArgumentException) {
        }

        return null;
    }
}
