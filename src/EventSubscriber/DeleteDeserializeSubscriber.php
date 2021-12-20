<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\EventSubscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use ApiPlatform\Core\Util\RequestAttributesExtractor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class DeleteDeserializeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly SerializerContextBuilderInterface $serializerContextBuilder
    ) {
    }

    /**
     * @return array<string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['deserializeForDelete', EventPriorities::POST_DESERIALIZE],
        ];
    }

    public function deserializeForDelete(RequestEvent $requestEvent): void
    {
        $request = $requestEvent->getRequest();
        $method = $request->getMethod();

        if ($method !== Request::METHOD_DELETE) {
            return;
        }

        $data = $request->attributes->get('data');

        if ($data !== null) {
            return;
        }

        $attributes = RequestAttributesExtractor::extractAttributes($request);
        $context = $this->serializerContextBuilder->createFromRequest($request, false, $attributes);

        $request->attributes->set(
            'data',
            $this->serializer->deserialize(
                (string) json_encode($context['path_parameters'], JSON_THROW_ON_ERROR),
                $context['resource_class'],
                'json',
                $context
            )
        );
    }
}
