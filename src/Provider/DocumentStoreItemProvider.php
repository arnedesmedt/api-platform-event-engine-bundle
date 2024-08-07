<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Provider;

use ApiPlatform\Metadata\Operation;
use EventEngine\Data\ImmutableRecord;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * @template T of ImmutableRecord
 * @extends Provider<T>
 */
#[AutoconfigureTag('api_platform.state_provider')]
final class DocumentStoreItemProvider extends Provider
{
    /**
     * @param array<string, mixed> $uriVariables
     * @param array<mixed> $context
     *
     * @return T|null
     *
     * @inheritDoc
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|iterable|null
    {
        $message = $this->needMessage($context, $operation->getName());

        /** @var T|null $result */
        $result = $this->eventEngine->produce($message);

        return $result;
    }
}
