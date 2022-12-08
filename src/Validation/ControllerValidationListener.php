<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Validation;

use ADS\Bundle\ApiPlatformEventEngineBundle\Serializer\CustomContextBuilder;
use ApiPlatform\Symfony\EventListener\ValidateListener;
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
        $message = CustomContextBuilder::messageFromRequest($request);

        if ($message !== null) {
            return;
        }

        $this->validateListener->onKernelView($event);
    }
}
