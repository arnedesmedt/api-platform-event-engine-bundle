<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\EventSubscriber;

use EventEngine\Messaging\MessageBag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class EmptyResponseSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => ['fromEmptyArrayToEmptyObject', 15],
        ];
    }

    public function fromEmptyArrayToEmptyObject(ViewEvent $event): void
    {
        $result = $event->getControllerResult();
        /** @var MessageBag|null $message */
        $message = $event->getRequest()->attributes->get('message');

        if ($result !== 'null' || $message === null || $message->messageType() !== MessageBag::TYPE_COMMAND) {
            return;
        }

        $event->setControllerResult('{}');
    }
}
