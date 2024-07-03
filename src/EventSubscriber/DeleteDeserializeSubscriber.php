<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\EventSubscriber;

use ApiPlatform\Serializer\SerializerContextBuilderInterface;
use ApiPlatform\Symfony\EventListener\EventPriorities;
use ApiPlatform\Util\RequestAttributesExtractor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\SerializerInterface;

use function json_encode;

use const JSON_THROW_ON_ERROR;

#[AsEventListener(
    event: KernelEvents::REQUEST,
    method: 'deserializeForDelete',
    priority: EventPriorities::POST_DESERIALIZE,
)]
final class DeleteDeserializeSubscriber
{
    public function __construct(
        #[Autowire('@api_platform.serializer')]
        private SerializerInterface $serializer,
        #[Autowire('@api_platform.serializer.context_builder')]
        private SerializerContextBuilderInterface $serializerContextBuilder,
    ) {
    }

    public function deserializeForDelete(RequestEvent $requestEvent): void
    {
        // todo check what to do with this one?
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
                (string) json_encode($context['path_parameters'] ?? '', JSON_THROW_ON_ERROR),
                $context['resource_class'] ?? '',
                'json',
                $context,
            ),
        );
    }
}
