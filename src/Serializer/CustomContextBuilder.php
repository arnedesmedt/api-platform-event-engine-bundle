<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\Bundle\ApiPlatformEventEngineBundle\ApiResource\ChangeApiResource;
use ADS\Bundle\ApiPlatformEventEngineBundle\Util\Util;
use ADS\Bundle\EventEngineBundle\Util\StringUtil;
use ApiPlatform\Core\Api\IdentifiersExtractorInterface;
use ApiPlatform\Core\Serializer\SerializerContextBuilder;
use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;

use function array_filter;
use function array_map;
use function reset;
use function strpos;

use const ARRAY_FILTER_USE_KEY;

final class CustomContextBuilder implements SerializerContextBuilderInterface
{
    private SerializerContextBuilderInterface $decorated;
    private IdentifiersExtractorInterface $identifiersExtractor;

    public function __construct(
        SerializerContextBuilder $decorated,
        IdentifiersExtractorInterface $identifiersExtractor
    ) {
        $this->decorated = $decorated;
        $this->identifiersExtractor = $identifiersExtractor;
    }

    /**
     * @param array<mixed>|null $extractedAttributes
     *
     * @return array<mixed>
     */
    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        $context = $this->extractPathParameters($request, $context);
        $context = $this->changeResourceClass($context);
        $context = $this->addIdentifier($request, $context);

        return $context;
    }

    /**
     * @param array<mixed> $context
     *
     * @return array<mixed>
     */
    private function extractPathParameters(Request $request, array $context): array
    {
        $pathParameters = array_filter(
            $request->attributes->get('_route_params'),
            static function (string $attributeKey) {
                return strpos($attributeKey, '_') !== 0;
            },
            ARRAY_FILTER_USE_KEY
        );

        $context['pathParameters'] = array_map(
            static function (string $pathParameter) {
                return Util::castFromString($pathParameter);
            },
            $pathParameters
        );

        return $context;
    }

    /**
     * @param array<mixed> $context
     *
     * @return array<mixed>
     */
    private function changeResourceClass(array $context): array
    {
        $reflectionClass = new ReflectionClass($context['resource_class']);

        if ($reflectionClass->implementsInterface(ChangeApiResource::class)) {
            $context['changed_resource_class'] = $context['resource_class']::__newApiResource();
        }

        return $context;
    }

    /**
     * @param array<mixed> $context
     *
     * @return array<mixed>
     */
    private function addIdentifier(Request $request, array $context): array
    {
        if ($request->getMethod() === Request::METHOD_POST || $request->attributes->has('id')) {
            return $context;
        }

        $identifiers = $this->identifiersExtractor->getIdentifiersFromResourceClass($context['resource_class']);

        if (empty($identifiers)) {
            return $context;
        }

        $identifier = reset($identifiers);
        $identifier = StringUtil::decamilize($identifier);

        if ($request->attributes->has($identifier)) {
            $request->attributes->set('id', $request->attributes->get($identifier));
        }

        return $context;
    }
}
