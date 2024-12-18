<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\Bundle\ApiPlatformEventEngineBundle\Exception\BadRequestHttpException;
use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\FilterFinder;
use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\SearchFilter;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\EventEngineBundle\Command\Command;
use ADS\Bundle\EventEngineBundle\Query\Query;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use Chrisguitarguy\RequestId\RequestIdStorage;
use EventEngine\Data\ImmutableRecord;
use EventEngine\EventEngine;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use TeamBlue\ValueObjects\Exception\PatternException;

use function array_diff_key;
use function array_key_exists;
use function array_merge;
use function assert;
use function is_array;
use function method_exists;

#[AutoconfigureTag('serializer.normalizer', ['priority' => -889])]
final class MessageNormalizer implements DenormalizerInterface
{
    public function __construct(
        private EventEngine $eventEngine,
        private FilterFinder $filterFinder,
        #[Autowire('@ADS\Bundle\ApiPlatformEventEngineBundle\Serializer\ImmutableRecordNormalizer')]
        private readonly DenormalizerInterface $denormalizer,
        private readonly RequestIdStorage $requestIdStorage,
        #[Autowire('%api_platform.collection.pagination.page_parameter_name%')]
        private string $pageParameterName = 'page',
        #[Autowire('%api_platform.collection.order_parameter_name%')]
        private string $orderParameterName = 'order',
        #[Autowire('%api_platform.collection.pagination.items_per_page_parameter_name%')]
        private string $itemsPerPageParameterName = 'items-per-page',
    ) {
    }

    /** @param array<string, mixed> $context **/
    public function denormalize(mixed $data, string $type, string|null $format = null, array $context = []): mixed
    {
        $data = $this->data($data, $type, $context);
        $context['message_as_array'] = true;
        /** @var array<string, mixed> $message */
        $message = $this->denormalizer->denormalize($data, $type, $format, $context);

        /** @var array<string, string> $input */
        $input = $context['input'];

        // First we need to create the immutable record to convert for example string values to integers.
        // For example the byte value object can have a string as output but needs an integer according the schema.
        // And since the schema generator doesn't allow multiple types, we first need to make the transition.
        /** @var class-string<ImmutableRecord> $messageClass */
        $messageClass = $input['class'];
        try {
            $message = $messageClass::fromArray($message);
        } catch (PatternException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        return $this->eventEngine->messageFactory()->createMessageFromArray(
            $messageClass,
            [
                'payload' => $message->toArray(),
                'uuid' => $this->requestIdStorage->getRequestId(),
            ],
        );
    }

    /** @param array<mixed> $context */
    public function supportsDenormalization(
        mixed $data,
        string $type,
        string|null $format = null,
        array $context = [],
    ): bool {
        return ($context['input'] ?? false)
            && $this->denormalizer->supportsDenormalization($data, $type, $format);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<mixed>
     */
    private function data(mixed $data, string $type, array $context): array
    {
        /** @var class-string<ApiPlatformMessage> $messageClass */
        $messageClass = self::needMessageClassFromContext($context);

        if (
            method_exists($messageClass, '__requestBodyArrayProperty')
            && $messageClass::__requestBodyArrayProperty()
        ) {
            $data = [$messageClass::__requestBodyArrayProperty() => $data];
        }

        assert(is_array($data));

        /** @var Operation $operation */
        $operation = $context['operation'];
        $filter = ($this->filterFinder)($operation, SearchFilter::class);

        /** @var array<string, string> $pathParameters */
        $pathParameters = $context['uri_variables'] ?? [];

        // todo how to handle query parameters? They won't be anymore in the context
        /** @var array<string, string> $queryParameters */
        $queryParameters = $context['query_parameters'] ?? [];

        if ($filter !== null) {
            $descriptions = $filter->getDescription($type);
            $queryParameters = array_diff_key($queryParameters, $descriptions);
        }

        if (array_key_exists($this->pageParameterName, $queryParameters)) {
            unset($queryParameters[$this->pageParameterName]);
        }

        if (array_key_exists($this->orderParameterName, $queryParameters)) {
            unset($queryParameters[$this->orderParameterName]);
        }

        if (array_key_exists($this->itemsPerPageParameterName, $queryParameters)) {
            unset($queryParameters[$this->itemsPerPageParameterName]);
        }

        return array_merge(
            $data,
            $pathParameters,
            $queryParameters,
        );
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return class-string<Query|Command|JsonSchemaAwareRecord>
     */
    public static function needMessageClassFromContext(array $context): string
    {
        $messageClass = self::messageClassFromContext($context);

        if ($messageClass === null) {
            throw new RuntimeException('No message class found in the context.');
        }

        return $messageClass;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return class-string<Query|Command|JsonSchemaAwareRecord>
     */
    public static function messageClassFromContext(array $context): string|null
    {
        /** @var HttpOperation|null $operation */
        $operation = $context['operation'] ?? null;

        return $operation?->getInput()['class'] ?? null;
    }

    /** @inheritDoc */
    public function getSupportedTypes(string|null $format): array
    {
        return [ImmutableRecord::class => true];
    }
}
