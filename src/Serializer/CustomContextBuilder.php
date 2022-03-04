<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\Bundle\EventEngineBundle\Type\Type as EventEngineType;
use ADS\Util\StringUtil;
use ApiPlatform\Core\Api\IdentifiersExtractorInterface;
use ApiPlatform\Core\Serializer\SerializerContextBuilder;
use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

use function array_filter;
use function array_map;
use function is_string;
use function reset;
use function settype;
use function str_starts_with;

use const ARRAY_FILTER_USE_KEY;

final class CustomContextBuilder implements SerializerContextBuilderInterface
{
    public function __construct(
        private SerializerContextBuilder $decorated,
        private IdentifiersExtractorInterface $identifiersExtractor
    ) {
    }

    /**
     * @param array<mixed>|null $extractedAttributes
     *
     * @return array<mixed>
     */
    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        $this->extractPathParameters($request, $context);
        $this->extractQueryParameters($request, $context);
        $this->addIdentifier($request, $context);
        $this->addMessage($request, $context);
        $context[AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS] = false;

        return $context;
    }

    /**
     * @param array<mixed> $context
     */
    private function extractPathParameters(Request $request, array &$context): void
    {
        /** @var array<string, string> $routeParameters */
        $routeParameters = $request->attributes->get('_route_params', []);
        $pathParameters = array_filter(
            $routeParameters,
            static fn (string $attributeKey) => ! str_starts_with($attributeKey, '_'),
            ARRAY_FILTER_USE_KEY
        );

        /** @var class-string $resourceClass */
        $resourceClass = $context['resource_class'];
        $resourceClassInstance = (new ReflectionClass($resourceClass))->newInstanceWithoutConstructor();

        /**
         * @var string $pathParameterName
         * @var string $pathParameterValue
         */
        foreach ($pathParameters as $pathParameterName => $pathParameterValue) {
            $propertyType = $this->extactPropertyTypeFromResourceClass($resourceClassInstance, $pathParameterName);

            if ($propertyType !== null) {
                settype($pathParameterValue, $propertyType);
            } else {
                $pathParameterValue = StringUtil::castFromString($pathParameterValue);
            }

            $pathParameters[$pathParameterName] = $pathParameterValue;
        }

        $context['path_parameters'] = $pathParameters;
    }

    private function extactPropertyTypeFromResourceClass(mixed $resourceClassInstance, string $propertyName): ?string
    {
        if (! $resourceClassInstance instanceof EventEngineType) {
            return null;
        }

        $properties = $resourceClassInstance
            ->__schema()
            ->toArray()['properties'];

        if (! isset($properties[StringUtil::camelize($propertyName)])) {
            return null;
        }

        $type = $properties[StringUtil::camelize($propertyName)]['type'];

        return $type === 'number' ? 'float' : $type;
    }

    /**
     * @param array<mixed> $context
     */
    private function extractQueryParameters(Request $request, array &$context): void
    {
        $context['query_parameters'] = array_map(
            static function ($queryParameter) {
                if (is_string($queryParameter)) {
                    return StringUtil::castFromString($queryParameter);
                }

                return array_map(
                    static fn (string $queryParameterItem) => StringUtil::castFromString($queryParameterItem),
                    $queryParameter
                );
            },
            $request->query->all()
        );
    }

    /**
     * @param array<mixed> $context
     */
    private function addIdentifier(Request $request, array &$context): void
    {
        if ($request->getMethod() === Request::METHOD_POST || $request->attributes->has('id')) {
            return;
        }

        /** @var class-string $resourceClass */
        $resourceClass = $context['resource_class'];
        $identifiers = $this->identifiersExtractor->getIdentifiersFromResourceClass($resourceClass);

        if (empty($identifiers)) {
            return;
        }

        $identifier = reset($identifiers);
        $identifier = StringUtil::decamelize($identifier);

        if (! $request->attributes->has($identifier)) {
            return;
        }

        $request->attributes->set('id', $request->attributes->get($identifier));
    }

    /**
     * @param array<mixed> $context
     */
    private function addMessage(Request $request, array &$context): void
    {
        if (! $request->attributes->get('message')) {
            return;
        }

        $context['message'] = $request->attributes->get('message');
    }
}
