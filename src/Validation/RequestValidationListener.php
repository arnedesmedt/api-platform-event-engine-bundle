<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Validation;

use ApiPlatform\Core\EventListener\EventPriorities;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Util\RequestAttributesExtractor;
use ApiPlatform\Core\Validator\ValidatorInterface;
use EventEngine\Messaging\MessageBag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class RequestValidationListener implements EventSubscriberInterface
{
    private ValidatorInterface $validator;
    private ResourceMetadataFactoryInterface $resourceMetadataFactory;

    public function __construct(
        ValidatorInterface $validator,
        ResourceMetadataFactoryInterface $resourceMetadataFactory
    ) {
        $this->validator = $validator;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['validateMessage', EventPriorities::POST_DESERIALIZE],
        ];
    }

    public function validateMessage(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $attributes = RequestAttributesExtractor::extractAttributes($request);
        $resourceMetadata = $this->resourceMetadataFactory->create($attributes['resource_class']);
        $validationGroups = $resourceMetadata->getOperationAttribute($attributes, 'validation_groups', null, true);

        /** @var MessageBag|null $message */
        $message = $request->attributes->get('message');

        if ($message === null) {
            return;
        }

        $this->validator->validate($message, ['groups' => $validationGroups]);
    }
}
