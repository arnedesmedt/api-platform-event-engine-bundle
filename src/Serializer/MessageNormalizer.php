<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\FilterFinder;
use ADS\Bundle\ApiPlatformEventEngineBundle\Filter\SearchFilter;
use ADS\Bundle\ApiPlatformEventEngineBundle\Message\ApiPlatformMessage;
use ADS\Bundle\EventEngineBundle\Command\Command;
use ADS\Bundle\EventEngineBundle\Messenger\Queueable;
use ADS\Bundle\EventEngineBundle\Query\Query;
use ADS\Util\StringUtil;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use EventEngine\EventEngine;
use RuntimeException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

use function array_diff_key;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_merge;
use function assert;
use function class_implements;
use function in_array;
use function is_array;
use function method_exists;

final class MessageNormalizer implements DenormalizerInterface
{
    public function __construct(
        private EventEngine $eventEngine,
        private FilterFinder $filterFinder,
        private readonly DenormalizerInterface $denormalizer,
        private string $pageParameterName = 'page',
        private string $orderParameterName = 'order',
        private string $itemsPerPageParameterName = 'items-per-page'
    ) {
    }

    /**
     * @param array<string, mixed> $context
     **/
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $data = $this->data($data, $type, $context);
        $context['message_as_array'] = true;
        $message = $this->denormalizer->denormalize($data, $type, $format, $context);

        /** @var array<string, string> $input */
        $input = $context['input'];

        return $this->eventEngine->messageFactory()->createMessageFromArray(
            $input['class'],
            [
                'payload' => $message,
                'metadata' => $this->metadata($context),
            ]
        );
    }

    /**
     * @param array<mixed> $context
     */
    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
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

        /** @var array<string, string> $uriVariables */
        $uriVariables = $context['uri_variables'] ?? [];
        $pathParameters = array_map(
            static fn (string $uriVariable) => StringUtil::castFromString($uriVariable),
            array_filter($uriVariables),
        );

        // todo how to handle query parameters? They won't be anymore in the context
        /** @var array<string, mixed> $queryParameters */
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
            $queryParameters
        );
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function metadata(array $context): array
    {
        /** @var class-string<Queueable>|null $messageClass */
        $messageClass = self::messageClassFromContext($context);

        if ($messageClass === null) {
            return [];
        }

        $interfaces = class_implements($messageClass);

        if ($interfaces === false || ! in_array(Queueable::class, $interfaces)) {
            return [];
        }

        return [
            'async' => $messageClass::__dispatchAsync(),
        ];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return class-string<Query|Command>
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
     * @return class-string<Query|Command>
     */
    public static function messageClassFromContext(array $context): string|null
    {
        /** @var HttpOperation|null $operation */
        $operation = $context['operation'] ?? null;

        return $operation?->getInput()['class'] ?? null;
    }
}
