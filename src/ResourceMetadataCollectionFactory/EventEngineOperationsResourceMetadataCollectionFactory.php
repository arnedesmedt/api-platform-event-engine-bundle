<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ResourceMetadataCollectionFactory;

use ADS\Bundle\ApiPlatformEventEngineBundle\Attribute\EventEngineResource;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;

use Symfony\Component\Finder\Finder;
use function is_a;

final class EventEngineOperationsResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    use EventEngineResourceMetdataCollectionFactoryLogic;

    public function __construct(
        private readonly Finder $finder,
        private readonly ResourceMetadataCollectionFactoryInterface|null $decorated = null,
    ) {
    }

    protected function decorateEventEngineResource(EventEngineResource $eventEngineResource): ApiResource
    {
        $commandFolders = $eventEngineResource->commandFolders();
        $messageFiles = $this->finder->in($commandFolders)->files();

        $operations = $eventEngineResource->getOperations() ?? new Operations();

        foreach($messageFiles as $messageFile) {
            $messageClass = $messageFile->
        }


        return $eventEngineResource->withOperations(
            $eventEngineResource->getOperations(),
        );
    }
}
