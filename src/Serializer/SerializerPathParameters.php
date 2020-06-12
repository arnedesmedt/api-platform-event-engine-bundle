<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\Bundle\ApiPlatformEventEngineBundle\Util\Util;
use ApiPlatform\Core\Serializer\SerializerContextBuilder;
use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use function array_filter;
use function array_map;
use function strpos;
use const ARRAY_FILTER_USE_KEY;

final class SerializerPathParameters implements SerializerContextBuilderInterface
{
    private SerializerContextBuilderInterface $decorated;

    public function __construct(SerializerContextBuilder $decorated)
    {
        $this->decorated = $decorated;
    }

    /**
     * @param array<mixed>|null $extractedAttributes
     *
     * @return array<mixed>
     */
    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null) : array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

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
}
