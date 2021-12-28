<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\EventSubscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use ApiPlatform\Core\Util\RequestAttributesExtractor;
use EventEngine\Messaging\MessageBag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;

final class MessageDeserializeSubscriber implements EventSubscriberInterface
{
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
            KernelEvents::REQUEST => ['messageDeserialize', EventPriorities::PRE_READ],
        ];
    }

    public function messageDeserialize(RequestEvent $event): void
    {
        if (! $event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();
        $attributes = RequestAttributesExtractor::extractAttributes($request);

        if (empty($attributes)) {
            return;
        }

        $context = $this->serializerContextBuilder->createFromRequest($request, true, $attributes);
        $content = $request->getContent();

        if (empty($content)) {
            $content = '{}';
        }

        $message = $this->deserializer->deserialize(
            $content,
            $attributes['resource_class'],
            (string) $request->getRequestFormat('application/json'),
            $context
        );

        if (! $message instanceof MessageBag) {
            return;
        }

        $request->attributes->set('message', $message);
    }
}
