<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Provider;

use ApiPlatform\Metadata\Operation;

final class DocumentStoreItemProvider extends Provider
{
    /**
     * @param array<string, mixed> $uriVariables
     * @param array<mixed> $context
     *
     * @return array<mixed>|object|null
     *
     * @inheritDoc
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = [])
    {
        $message = $this->needMessage($context, $operation->getName());

        /** @var object|array<mixed>|null $result */
        $result = $this->eventEngine->produce($message);

        return $result;
    }
}
