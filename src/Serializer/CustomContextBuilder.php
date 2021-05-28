<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\Util\StringUtil;
use ApiPlatform\Core\Api\IdentifiersExtractorInterface;
use ApiPlatform\Core\Serializer\SerializerContextBuilder;
use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

use function array_filter;
use function array_map;
use function is_string;
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

        $this->extractPathParameters($request, $context);
        $this->extractQueryParameters($request, $context);
        $this->addIdentifier($request, $context);
        $this->addMessage($request, $context);
        $context[AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS] = true;

        return $context;
    }

    /**
     * @param array<mixed> $context
     */
    private function extractPathParameters(Request $request, array &$context): void
    {
        $pathParameters = array_filter(
            $request->attributes->get('_route_params'),
            static function (string $attributeKey) {
                return strpos($attributeKey, '_') !== 0;
            },
            ARRAY_FILTER_USE_KEY
        );

        $context['path_parameters'] = array_map(
            static function (string $pathParameter) {
                return StringUtil::castFromString($pathParameter);
            },
            $pathParameters
        );
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

        $identifiers = $this->identifiersExtractor->getIdentifiersFromResourceClass($context['resource_class']);

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
