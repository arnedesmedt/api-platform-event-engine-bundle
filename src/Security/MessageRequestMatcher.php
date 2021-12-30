<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Security;

use ADS\Bundle\ApiPlatformEventEngineBundle\Message\Finder;
use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use ApiPlatform\Core\Util\RequestAttributesExtractor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

abstract class MessageRequestMatcher implements RequestMatcherInterface
{
    public function __construct(
        private Finder $finder,
        private SerializerContextBuilderInterface $serializerContextBuilder,
    ) {
    }

    /**
     * @return class-string|string
     */
    public function message(Request $request): string
    {
        $attributes = RequestAttributesExtractor::extractAttributes($request);
        $context = $this->serializerContextBuilder->createFromRequest($request, true, $attributes);

        return $this->finder->byContext($context);
    }
}
