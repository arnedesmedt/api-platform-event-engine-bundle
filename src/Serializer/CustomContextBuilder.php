<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Finder;
use ADS\Util\StringUtil;
use ApiPlatform\Core\Api\IdentifiersExtractorInterface;
use ApiPlatform\Core\Serializer\SerializerContextBuilder;
use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

use function array_diff;
use function array_filter;
use function array_unique;
use function is_array;
use function is_string;
use function reset;
use function settype;
use function str_starts_with;

use const ARRAY_FILTER_USE_KEY;

final class CustomContextBuilder implements SerializerContextBuilderInterface
{
    public function __construct(
        private SerializerContextBuilder $decorated,
        private IdentifiersExtractorInterface $identifiersExtractor,
        private Finder $messageFinder,
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

        $this
            ->addMessageClass($context)
            ->extractPathParameters($request, $context)
            ->extractQueryParameters($request, $context)
            ->addIdentifier($request, $context)
            ->addMessage($request, $context);

        $context[AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS] = false;

        return $context;
    }

    /**
     * @param array<mixed> $context
     */
    private function addMessageClass(array &$context): self
    {
        if ($this->messageFinder->hasMessageByContext($context)) {
            $context['message_class'] = $this->messageFinder->byContext($context);
        }

        return $this;
    }

    /**
     * @param array<mixed> $context
     */
    private function extractPathParameters(Request $request, array &$context): self
    {
        /** @var array<string, string> $routeParameters */
        $routeParameters = $request->attributes->get('_route_params', []);
        $pathParameters = array_filter(
            $routeParameters,
            static fn (string $attributeKey) => ! str_starts_with($attributeKey, '_'),
            ARRAY_FILTER_USE_KEY
        );

        $context['path_parameters'] = $this->castParameters(
            $this->propertiesFromContext($context),
            $pathParameters
        );

        return $this;
    }

    /**
     * @param array<mixed> $context
     */
    private function extractQueryParameters(Request $request, array &$context): self
    {
        $context['query_parameters'] = $this->castParameters(
            $this->propertiesFromContext($context),
            $request->query->all()
        );

        return $this;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function propertiesFromContext(array $context): array
    {
        /** @var class-string<JsonSchemaAwareRecord> $messageClass */
        $messageClass = $context['message_class'] ?? $context['resource_class'];

        if (! (new ReflectionClass($messageClass))->implementsInterface(JsonSchemaAwareRecord::class)) {
            return [];
        }

        return $messageClass::__schema()->toArray()['properties'] ?? [];
    }

    /**
     * @param array<string, mixed> $propertySchemas
     * @param array<string, mixed> $parameters
     *
     * @return array<mixed>
     */
    private function castParameters(array $propertySchemas, array $parameters): array
    {
        foreach ($parameters as $parameterName => $parameterValue) {
            /** @var array<string, mixed>|null $propertySchema */
            $propertySchema = $propertySchemas[StringUtil::camelize($parameterName)] ?? null;

            $parameters[$parameterName] = $this->castParameter($propertySchema, $parameterValue);
        }

        return $parameters;
    }

    /**
     * @param array<string, mixed>|null $propertySchema
     */
    private function castParameter(?array $propertySchema, mixed $parameterValue): mixed
    {
        /** @var string|array<string>|null $propertyType */
        $propertyType = $propertySchema['type'] ?? null;
        $propertyType = match ($propertyType) {
            'number' => 'float',
            default => $propertyType
        };

        if (is_array($propertyType)) {
            // multiple types are set or the value is nullable
            $propertyTypes = array_unique(array_diff($propertyType, ['null']));
            $propertyType = reset($propertyTypes);

            if ($propertyType === false) {
                $propertyType = null;
            }
        }

        if ($propertyType === 'array') {
            /** @var array<string, mixed> $itemPropertySchema */
            $itemPropertySchema = $propertySchema['items'] ?? [];

            if (! is_array($parameterValue)) {
                $parameterValue = [$parameterValue];
            }

            foreach ($parameterValue as $key => $parameterValueItem) {
                $parameterValue[$key] = $this->castParameter($itemPropertySchema, $parameterValueItem);
            }

            return $parameterValue;
        } elseif ($propertyType !== null) {
            settype($parameterValue, $propertyType);
        } elseif (is_string($parameterValue)) {
            $parameterValue = StringUtil::castFromString($parameterValue);
        }

        return $parameterValue;
    }

    /**
     * @param array<mixed> $context
     */
    private function addIdentifier(Request $request, array &$context): self
    {
        if ($request->getMethod() === Request::METHOD_POST || $request->attributes->has('id')) {
            return $this;
        }

        /** @var class-string $resourceClass */
        $resourceClass = $context['resource_class'];
        $identifiers = $this->identifiersExtractor->getIdentifiersFromResourceClass($resourceClass);

        if (empty($identifiers)) {
            return $this;
        }

        $identifier = reset($identifiers);
        $identifier = StringUtil::decamelize($identifier);

        if (! $request->attributes->has($identifier)) {
            return $this;
        }

        $request->attributes->set('id', $request->attributes->get($identifier));

        return $this;
    }

    /**
     * @param array<mixed> $context
     */
    private function addMessage(Request $request, array &$context): self
    {
        if (! $request->attributes->has('message')) {
            return $this;
        }

        $context['message'] = $request->attributes->get('message');

        return $this;
    }
}
