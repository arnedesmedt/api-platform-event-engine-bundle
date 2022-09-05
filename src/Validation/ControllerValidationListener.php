<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Validation;

use ApiPlatform\Symfony\EventListener\ValidateListener;
use EventEngine\Messaging\MessageBag;
use Symfony\Component\HttpKernel\Event\ViewEvent;

final class ControllerValidationListener
{
    private ValidateListener $validateListener;

    public function __construct(
        ValidateListener $validateListener,
    ) {
        $this->validateListener = $validateListener;
    }

    public function onKernelView(ViewEvent $event): void
    {
        $request = $event->getRequest();
        /** @var MessageBag|null $message */
        $message = $request->attributes->get('message');

        if ($message !== null) {
            return;
        }

        $this->validateListener->onKernelView($event);
    }
}
