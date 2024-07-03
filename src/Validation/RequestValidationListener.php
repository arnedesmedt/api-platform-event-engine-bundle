<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Validation;

use ADS\Bundle\ApiPlatformEventEngineBundle\Serializer\CustomContextBuilder;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Symfony\EventListener\EventPriorities;
use ApiPlatform\Util\RequestAttributesExtractor;
use ApiPlatform\Validator\ValidatorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(
    event: KernelEvents::REQUEST,
    method: 'validateMessage',
    priority: EventPriorities::PRE_READ,
)]
final class RequestValidationListener
{
    public function __construct(
        #[Autowire('@api_platform.validator')]
        private ValidatorInterface $validator,
        #[Autowire('@api_platform.metadata.resource.metadata_collection_factory')]
        private ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
    ) {
    }

    public function validateMessage(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $attributes = RequestAttributesExtractor::extractAttributes($request);
        $method = $request->getMethod();

        if (
            ($method !== Request::METHOD_DELETE && ! $request->isMethodSafe())
            || ! $attributes
            || ! $attributes['receive']
            || ! isset($attributes['resource_class'])
        ) {
            return;
        }

        $message = CustomContextBuilder::messageFromRequest($request);

        if ($message === null) {
            return;
        }

        $resourceMetadataCollection = $this->resourceMetadataCollectionFactory->create($attributes['resource_class']);

        foreach ($resourceMetadataCollection as $resourceMetadata) {
            $this->validator->validate($message, ['groups' => $resourceMetadata->getValidationContext()]);
        }
    }
}
