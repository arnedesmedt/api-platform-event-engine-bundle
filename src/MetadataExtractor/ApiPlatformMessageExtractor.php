<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\MetadataExtractor;

use ADS\Bundle\ApiPlatformEventEngineBundle\Attribute\ApiPlatformMessage as ApiPlatformMessageAttribute;
use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\ApiPlatformMappingException;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\ApiPlatformEventEngineBundle\Operation\Name;
use ADS\Bundle\ApiPlatformEventEngineBundle\Processor\CommandProcessor;
use ADS\Bundle\ApiPlatformEventEngineBundle\Provider\DocumentStoreCollectionProvider;
use ADS\Bundle\ApiPlatformEventEngineBundle\Provider\DocumentStoreItemProvider;
use ADS\Bundle\ApiPlatformEventEngineBundle\ValueObject\Uri;
use ADS\Bundle\EventEngineBundle\MetadataExtractor\CommandExtractor;
use ADS\Bundle\EventEngineBundle\MetadataExtractor\QueryExtractor;
use ADS\Util\MetadataExtractor\MetadataExtractor;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use InvalidArgumentException;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionClass;

use function lcfirst;
use function ltrim;
use function PHPUnit\Framework\isFalse;
use function preg_match;
use function sprintf;
use function ucfirst;

class ApiPlatformMessageExtractor
{
    private readonly DocBlockFactory $docBlockFactory;

    public function __construct(
        private readonly MetadataExtractor $metadataExtractor,
        private readonly EventEngineResourceFinder $eventEngineResourceFinder,
        private readonly CommandExtractor $commandExtractor,
        private readonly QueryExtractor $queryExtractor,
    ) {
        $this->docBlockFactory = DocBlockFactory::createInstance();
    }

    /** @param ReflectionClass<object> $reflectionClass */
    public function resourceClassFromReflectionClass(ReflectionClass $reflectionClass): string
    {
        /** @var string|null $resourceClass */
        $resourceClass = $this->metadataExtractor->needMetadataFromReflectionClass(
            $reflectionClass,
            [
                ApiPlatformMessageAttribute::class => static fn (ApiPlatformMessageAttribute $attribute) => $attribute
                    ->resource(),
                /** @param class-string<ApiPlatformMessage> $class */
                ApiPlatformMessage::class => static fn (string $class) => $class::__resource(),
            ],
        );

        if ($resourceClass === null) {
            $resourceClass = $this->eventEngineResourceFinder->withMatchingNamespace($reflectionClass);
        }

        return $resourceClass;
    }

    /** @param ReflectionClass<object> $reflectionClass */
    public function httpMethodFromReflectionClass(ReflectionClass $reflectionClass): string
    {
        /** @var string $httpMethod */
        $httpMethod = $this->metadataExtractor->needMetadataFromReflectionClass(
            $reflectionClass,
            [
                ApiPlatformMessageAttribute::class => static fn (ApiPlatformMessageAttribute $attribute) => $attribute
                    ->httpMethod(),
                /** @param class-string<ApiPlatformMessage> $class */
                ApiPlatformMessage::class => static fn (string $class) => $class::__httpMethod(),
            ],
        );

        return $httpMethod;
    }

    /** @param ReflectionClass<object> $reflectionClass */
    public function uriTemplateFromReflectionClass(ReflectionClass $reflectionClass): string
    {
        /** @var string $uriTemplate */
        $uriTemplate = $this->metadataExtractor->needMetadataFromReflectionClass(
            $reflectionClass,
            [
                ApiPlatformMessageAttribute::class => static fn (ApiPlatformMessageAttribute $attribute) => $attribute
                    ->uriTemplate(),
                /** @param class-string<ApiPlatformMessage> $class */
                ApiPlatformMessage::class => static fn (string $class) => $class::__uriTemplate(),
            ],
        );

        return '/' . ltrim(Uri::fromString($uriTemplate)->toUrlPart(), '/');
    }

    /** @param ReflectionClass<object> $reflectionClass */
    public function operationIdFromReflectionClass(ReflectionClass $reflectionClass): string
    {
        /** @var string|null $operationId */
        $operationId = $this->metadataExtractor->needMetadataFromReflectionClass(
            $reflectionClass,
            [
                ApiPlatformMessageAttribute::class => static fn (ApiPlatformMessageAttribute $attribute) => $attribute
                    ->operationId(),
                /** @param class-string<ApiPlatformMessage> $class */
                ApiPlatformMessage::class => static fn (string $class) => $class::__operationId(),
            ],
        );

        if ($operationId === null) {
            $resourceClass = $this->resourceClassFromReflectionClass($reflectionClass);
            $operationId = sprintf(
                '%s%s%s',
                lcfirst($this->operationNameFromReflectionClass($reflectionClass)),
                ucfirst((new ReflectionClass($resourceClass))->getShortName()),
                $this->isCollectionFromReflectionClass($reflectionClass) ? 'Collection' : 'Item',
            );
        }

        return $operationId;
    }

    /** @param ReflectionClass<object> $reflectionClass */
    public function operationNameFromReflectionClass(ReflectionClass $reflectionClass): string
    {
        /** @var string|null $operationName */
        $operationName = $this->metadataExtractor->needMetadataFromReflectionClass(
            $reflectionClass,
            [
                ApiPlatformMessageAttribute::class => static fn (ApiPlatformMessageAttribute $attribute) => $attribute
                    ->operationName(),
                /** @param class-string<ApiPlatformMessage> $class */
                ApiPlatformMessage::class => static fn (string $class) => $class::__operationName(),
            ],
        );

        if ($operationName === null) {
            $shortName = $reflectionClass->getShortName();
            $operationName = match (true) {
                (bool) preg_match('/(Create|Add|Enable|Import)/', $shortName) => Name::POST,
                (bool) preg_match('/(Get|GetAll|All|ById|ByUuid)/', $shortName) => Name::GET,
                (bool) preg_match('/(Update)/', $shortName) => Name::PUT,
                (bool) preg_match('/(Change)/', $shortName) => Name::PATCH,
                (bool) preg_match('/(Delete|Remove|Disable)/', $shortName) => Name::DELETE,
                default => throw ApiPlatformMappingException::noOperationNameFound(static::class), // todo use php-exception library
            };
        }

        return $operationName;
    }

    /** @param ReflectionClass<object> $reflectionClass */
    public function isCollectionFromReflectionClass(ReflectionClass $reflectionClass): bool
    {
        /** @var bool|null $isCollection */
        $isCollection = $this->metadataExtractor->needMetadataFromReflectionClass(
            $reflectionClass,
            [
                ApiPlatformMessageAttribute::class => static fn (ApiPlatformMessageAttribute $attribute) => $attribute
                    ->isCollection(),
                /** @param class-string<ApiPlatformMessage> $class */
                ApiPlatformMessage::class => static fn (string $class) => $class::__isCollection(),
            ],
        );

        if ($isCollection === null) {
            $isCollection = (bool) preg_match(
                '/(Create|Add|GetAll|All|Enable|Import)/',
                $reflectionClass->getShortName(),
            );
        }

        return $isCollection;
    }

    /** @param ReflectionClass<object> $reflectionClass */
    public function summaryFromReflectionClass(ReflectionClass $reflectionClass): string
    {
        /** @var string $summary */
        $summary = $this->metadataExtractor->needMetadataFromReflectionClass(
            $reflectionClass,
            [
                ApiPlatformMessageAttribute::class => static fn (ApiPlatformMessageAttribute $attribute) => $attribute
                    ->summary(),
                /** @param class-string<ApiPlatformMessage> $class */
                ApiPlatformMessage::class => function (string $class) use ($reflectionClass) {
                    try {
                        return $this->docBlockFactory->create($reflectionClass)->getSummary();
                    } catch (InvalidArgumentException) {
                        return null;
                    }
                },
            ],
        );

        return $summary;
    }

    /**
     * @param ReflectionClass<object> $reflectionClass
     *
     * @return array<string, string>|null
     */
    public function requirementsFromReflectionClass(ReflectionClass $reflectionClass): array|null
    {
        /** @var array<string, string>|null $requirements */
        $requirements = $this->metadataExtractor->needMetadataFromReflectionClass(
            $reflectionClass,
            [
                ApiPlatformMessageAttribute::class => static fn (ApiPlatformMessageAttribute $attribute) => $attribute
                    ->requirements(),
                /** @param class-string<ApiPlatformMessage> $class */
                ApiPlatformMessage::class => static fn (string $class) => $class::__requirements(),
            ],
        );

        return $requirements;
    }

    /**
     * @param ReflectionClass<object> $reflectionClass
     *
     * @return class-string<ProcessorInterface<object>>|null
     */
    public function processorFromReflectionClass(ReflectionClass $reflectionClass): string|null
    {
        if (! $this->commandExtractor->isCommandFromReflectionClass($reflectionClass)) {
            return null;
        }

        /** @var class-string<ProcessorInterface<object>>|null $processor */
        $processor = $this->metadataExtractor->needMetadataFromReflectionClass(
            $reflectionClass,
            [
                ApiPlatformMessageAttribute::class => static fn (ApiPlatformMessageAttribute $attribute) => $attribute
                    ->processor(),
                /** @param class-string<ApiPlatformMessage> $class */
                ApiPlatformMessage::class => static fn (string $class) => $class::__processor(),
            ],
        );

        return $processor ?? CommandProcessor::class;
    }

    /**
     * @param ReflectionClass<object> $reflectionClass
     *
     * @return class-string<ProviderInterface<object>>|null
     */
    public function providerFromReflectionClass(ReflectionClass $reflectionClass): string|null
    {
        if (! $this->queryExtractor->isQueryFromReflectionClass($reflectionClass)) {
            return null;
        }

        /** @var class-string<ProviderInterface<object>>|null $provider */
        $provider = $this->metadataExtractor->needMetadataFromReflectionClass(
            $reflectionClass,
            [
                ApiPlatformMessageAttribute::class => static fn (ApiPlatformMessageAttribute $attribute) => $attribute
                    ->provider(),
                /** @param class-string<ApiPlatformMessage> $class */
                ApiPlatformMessage::class => static fn (string $class) => $class::__provider(),
            ],
        );

        if ($provider) {
            return $provider;
        }

        return $this->isCollectionFromReflectionClass($reflectionClass)
            ? DocumentStoreCollectionProvider::class
            : DocumentStoreItemProvider::class;
    }
}
