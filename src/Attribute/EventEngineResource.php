<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Attribute;

use ADS\Bundle\EventEngineBundle\Command\Command;
use ADS\Bundle\EventEngineBundle\Query\Query;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\State\OptionsInterface;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class EventEngineResource extends ApiResource
{
    /**
     * @param array<string> $commandFolders
     * @param array<class-string<Command>> $commandClasses
     * @param array<string> $queryFolders
     * @param array<class-string<Query>> $queryClasses
     * @param array<string>|string|null $types
     * @param array<int, HttpOperation>|array<string, HttpOperation>|Operations|null $operations
     * @param array<string>|string|null $formats
     * @param array<string>|string|null $inputFormats
     * @param array<string>|string|null $outputFormats
     * @param array<string, Link>|array<string, mixed[]>|string[]|string|null $uriVariables
     * @param array<mixed>|null $defaults
     * @param array<mixed>|null $requirements
     * @param array<mixed>|null $options
     * @param array<mixed>|null $schemes
     * @param array<mixed>|null $cacheHeaders
     * @param array<mixed>|null $normalizationContext
     * @param array<mixed>|null $denormalizationContext
     * @param array<mixed>|null $hydraContext
     * @param array<mixed>|null $openapiContext
     * @param array<mixed>|null $validationContext
     * @param array<string>|null $filters
     * @param array<string>|null $order
     * @param array<mixed>|null $paginationViaCursor
     * @param array<mixed>|null $exceptionToStatus
     * @param array<mixed>|null $graphQlOperations
     * @param array<mixed> $extraProperties
     */
    public function __construct(
        private readonly array $commandFolders = [],
        private readonly array $commandClasses = [],
        private readonly array $queryFolders = [],
        private readonly array $queryClasses = [],
        string|null $uriTemplate = null,
        string|null $shortName = null,
        string|null $description = null,
        array|string|null $types = null,
        array|Operations|null $operations = null,
        array|string|null $formats = null,
        array|string|null $inputFormats = null,
        array|string|null $outputFormats = null,
        $uriVariables = null,
        string|null $routePrefix = null,
        array|null $defaults = null,
        array|null $requirements = null,
        array|null $options = null,
        bool|null $stateless = null,
        string|null $sunset = null,
        string|null $acceptPatch = null,
        int|null $status = null,
        string|null $host = null,
        array|null $schemes = null,
        string|null $condition = null,
        string|null $controller = null,
        string|null $class = null,
        int|null $urlGenerationStrategy = null,
        string|null $deprecationReason = null,
        array|null $cacheHeaders = null,
        array|null $normalizationContext = null,
        array|null $denormalizationContext = null,
        bool|null $collectDenormalizationErrors = null,
        array|null $hydraContext = null,
        array|null $openapiContext = null,
        OpenApiOperation|bool|null $openapi = null,
        array|null $validationContext = null,
        array|null $filters = null,
        bool|null $elasticsearch = null,
        mixed $mercure = null,
        string|bool|null $messenger = null,
        mixed $input = null,
        mixed $output = null,
        array|null $order = null,
        bool|null $fetchPartial = null,
        bool|null $forceEager = null,
        bool|null $paginationClientEnabled = null,
        bool|null $paginationClientItemsPerPage = null,
        bool|null $paginationClientPartial = null,
        array|null $paginationViaCursor = null,
        bool|null $paginationEnabled = null,
        bool|null $paginationFetchJoinCollection = null,
        bool|null $paginationUseOutputWalkers = null,
        int|null $paginationItemsPerPage = null,
        int|null $paginationMaximumItemsPerPage = null,
        bool|null $paginationPartial = null,
        string|null $paginationType = null,
        string|null $security = null,
        string|null $securityMessage = null,
        string|null $securityPostDenormalize = null,
        string|null $securityPostDenormalizeMessage = null,
        string|null $securityPostValidation = null,
        string|null $securityPostValidationMessage = null,
        bool|null $compositeIdentifier = null,
        array|null $exceptionToStatus = null,
        bool|null $queryParameterValidationEnabled = null,
        array|null $graphQlOperations = null,
        string|callable|null $provider = null,
        string|callable|null $processor = null,
        OptionsInterface|null $stateOptions = null,
        array $extraProperties = [],
    ) {
        parent::__construct(
            uriTemplate: $uriTemplate,
            shortName: $shortName,
            description: $description,
            types: $types,
            operations: $operations,
            formats: $formats,
            inputFormats: $inputFormats,
            outputFormats: $outputFormats,
            uriVariables: $uriVariables,
            routePrefix: $routePrefix,
            defaults: $defaults,
            requirements: $requirements,
            options: $options,
            stateless: $stateless,
            sunset: $sunset,
            acceptPatch: $acceptPatch,
            status: $status,
            host: $host,
            schemes: $schemes,
            condition: $condition,
            controller: $controller,
            class: $class,
            urlGenerationStrategy: $urlGenerationStrategy,
            deprecationReason: $deprecationReason,
            cacheHeaders: $cacheHeaders,
            normalizationContext: $normalizationContext,
            denormalizationContext: $denormalizationContext,
            collectDenormalizationErrors: $collectDenormalizationErrors,
            hydraContext: $hydraContext,
            openapiContext: $openapiContext,
            openapi: $openapi,
            validationContext: $validationContext,
            filters: $filters,
            elasticsearch: $elasticsearch,
            mercure: $mercure,
            messenger: $messenger,
            input: $input,
            output: $output,
            order: $order,
            fetchPartial: $fetchPartial,
            forceEager: $forceEager,
            paginationClientEnabled: $paginationClientEnabled,
            paginationClientItemsPerPage: $paginationClientItemsPerPage,
            paginationClientPartial: $paginationClientPartial,
            paginationViaCursor: $paginationViaCursor,
            paginationEnabled: $paginationEnabled,
            paginationFetchJoinCollection: $paginationFetchJoinCollection,
            paginationUseOutputWalkers: $paginationUseOutputWalkers,
            paginationItemsPerPage: $paginationItemsPerPage,
            paginationMaximumItemsPerPage: $paginationMaximumItemsPerPage,
            paginationPartial: $paginationPartial,
            paginationType: $paginationType,
            security: $security,
            securityMessage: $securityMessage,
            securityPostDenormalize: $securityPostDenormalize,
            securityPostDenormalizeMessage: $securityPostDenormalizeMessage,
            securityPostValidation: $securityPostValidation,
            securityPostValidationMessage: $securityPostValidationMessage,
            compositeIdentifier: $compositeIdentifier,
            exceptionToStatus: $exceptionToStatus,
            queryParameterValidationEnabled: $queryParameterValidationEnabled,
            graphQlOperations: $graphQlOperations,
            provider: $provider,
            processor: $processor,
            stateOptions: $stateOptions,
            extraProperties: $extraProperties,
        );
    }

    /** @return array<string> */
    public function commandFolders(): array
    {
        return $this->commandFolders;
    }

    /** @return array<class-string<Command>> */
    public function commandClasses(): array
    {
        return $this->commandClasses;
    }

    /** @return array<string> */
    public function queryFolders(): array
    {
        return $this->queryFolders;
    }

    /** @return array<class-string<Query>> */
    public function queryClasses(): array
    {
        return $this->queryClasses;
    }
}
