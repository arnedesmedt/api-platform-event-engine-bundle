<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Attribute;

use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use Attribute;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;

#[Attribute(Attribute::TARGET_CLASS)]
class ApiPlatformMessage
{
    /**
     * @param array<int, array<class-string<JsonSchemaAwareRecord>>> $responseClassesPerStatusCode
     * @param array<string, string> $requirements
     * @param array<string> $tags
     * @param class-string<ProcessorInterface<object>>|null $processor
     * @param class-string<ProviderInterface<object>>|null $provider
     */
    public function __construct(
        private readonly string $httpMethod,
        private readonly string $uriTemplate,
        private readonly array $requirements = [],
        private readonly string|null $resource = null,
        private readonly bool|null $isCollection = null,
        private readonly string|null $operationName = null,
        private readonly string|null $operationId = null,
        private readonly string|null $summary = null,
        private readonly string|null $description = null,
        private readonly array $tags = [],
        private readonly int|null $statusCode = null,
        private readonly array $responseClassesPerStatusCode = [],
        private readonly string|null $processor = null,
        private readonly string|null $provider = null,
    ) {
    }

    public function httpMethod(): string
    {
        return $this->httpMethod;
    }

    public function uriTemplate(): string
    {
        return $this->uriTemplate;
    }

    /**
     * Add requirements for variables in the uri template
     *
     * @return array<string, string>
     */
    public function requirements(): array
    {
        return $this->requirements;
    }

    /**
     * If null check for the nearest class that has the event engine state attribute
     */
    public function resource(): string|null
    {
        return $this->resource;
    }

    public function isCollection(): bool|null
    {
        return $this->isCollection;
    }

    public function operationName(): string|null
    {
        return $this->operationName;
    }

    public function operationId(): string|null
    {
        return $this->operationId;
    }

    public function summary(): string|null
    {
        return $this->summary;
    }

    public function description(): string|null
    {
        return $this->description;
    }

    /** @return array<string> */
    public function tags(): array
    {
        return $this->tags;
    }

    /** If null fetch the status code via the http method */
    public function statusCode(): int|null
    {
        return $this->statusCode;
    }

    /** @return array<int, array<class-string<JsonSchemaAwareRecord>>> */
    public function responseClassesPerStatusCode(): array
    {
        return $this->responseClassesPerStatusCode;
    }

    /** @return class-string<ProcessorInterface<object>> */
    public function processor(): string|null
    {
        return $this->processor;
    }

    /** @return class-string<ProviderInterface<object>> */
    public function provider(): string|null
    {
        return $this->provider;
    }
}
