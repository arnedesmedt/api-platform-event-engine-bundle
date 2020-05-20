<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ApiPlatform\Core\Documentation\Documentation;
use ApiPlatform\Core\Swagger\Serializer\DocumentationNormalizer as SwaggerDocumentationNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class DocumentationNormalizer implements NormalizerInterface
{
    /**
     * @param mixed $object
     * @param array<mixed> $context
     *
     * @return mixed
     */
    public function normalize($object, ?string $format = null, array $context = [])
    {
        return $object;
    }

    /**
     * @param mixed $data
     */
    public function supportsNormalization($data, ?string $format = null) : bool
    {
        return $format === SwaggerDocumentationNormalizer::FORMAT && $data instanceof Documentation;
    }
}
