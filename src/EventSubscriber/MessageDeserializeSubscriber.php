<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\EventSubscriber;

use ApiPlatform\Api\FormatMatcher;
use ApiPlatform\Serializer\SerializerContextBuilderInterface;
use ApiPlatform\Util\OperationRequestInitiatorTrait;
use ApiPlatform\Util\RequestAttributesExtractor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

use function implode;
use function sprintf;

final class MessageDeserializeSubscriber implements EventSubscriberInterface
{
    use OperationRequestInitiatorTrait;

    public function __construct(
        private SerializerContextBuilderInterface $serializerContextBuilder,
        private SerializerInterface $deserializer
    ) {
    }

    /**
     * @return array<string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['messageDeserialize', 6], // PRE_PRE_READ
        ];
    }

    public function messageDeserialize(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $attributes = RequestAttributesExtractor::extractAttributes($request);
        $method = $request->getMethod();

        if (
            ($method !== Request::METHOD_DELETE && ! $request->isMethodSafe())
            || ! $attributes
            || ! $attributes['receive']
        ) {
            return;
        }

        $operation = $this->initializeOperation($request);

        if (! ($operation?->canDeserialize() ?? true)) {
            return;
        }

        $context = $this->serializerContextBuilder->createFromRequest($request, false, $attributes);

        /** @var array<string, string[]> $formats */
        $formats = $operation?->getInputFormats() ?? [];
        $format = $method === Request::METHOD_DELETE
            ? 'json'
            : $this->getFormat($request, $formats);
        $data = $request->attributes->get('data');
        if ($data !== null) {
            $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $data;
        }

        $content = $request->getContent();
        if (empty($content)) {
            $content = '{}';
        }

        $request->attributes->set(
            'data',
            $this->deserializer->deserialize($content, $context['resource_class'], $format, $context)
        );
    }

    /**
     * @param array<string, string[]> $formats
     */
    private function getFormat(Request $request, array $formats): string
    {
        /** @var string $contentType */
        $contentType = $request->headers->get('CONTENT_TYPE', 'application/json');
        $formatMatcher = new FormatMatcher($formats);
        $format = $formatMatcher->getFormat($contentType);
        if ($format === null) {
            $supportedMimeTypes = [];
            foreach ($formats as $mimeTypes) {
                foreach ($mimeTypes as $mimeType) {
                    $supportedMimeTypes[] = $mimeType;
                }
            }

            throw new UnsupportedMediaTypeHttpException(
                sprintf(
                    'The content-type "%s" is not supported. Supported MIME types are "%s".',
                    $contentType,
                    implode('", "', $supportedMimeTypes)
                )
            );
        }

        return $format;
    }
}
