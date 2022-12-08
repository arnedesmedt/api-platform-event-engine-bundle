<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\Bundle\EventEngineBundle\Command\Command;
use ADS\Bundle\EventEngineBundle\Query\Query;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Serializer\SerializerContextBuilder;
use ApiPlatform\Serializer\SerializerContextBuilderInterface;
use EventEngine\Messaging\MessageBag;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

use function class_implements;
use function in_array;

final class CustomContextBuilder implements SerializerContextBuilderInterface
{
    public function __construct(
        private SerializerContextBuilder $decorated,
    ) {
    }

    /**
     * @param array<mixed>|null $extractedAttributes
     *
     * @return array<mixed>
     */
    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        $this
            ->addMessage($request, $context)
            ->extractQueryParameters($request, $context)
            ->dontPopulateForMessages($context);

        return $context;
    }

    /**
     * @param array<mixed> $context
     */
    private function addMessage(Request $request, array &$context): self
    {
        $message = self::messageFromRequest($request);

        if ($message === null) {
            return $this;
        }

        $context['message'] = $message;

        return $this;
    }

    /**
     * @param array<mixed> $context
     */
    private function extractQueryParameters(Request $request, array &$context): self
    {
        $context['query_parameters'] = $request->query->all();

        return $this;
    }

    public static function needMessageFromRequest(Request $request): MessageBag
    {
        $message = self::messageFromRequest($request);

        if ($message === null) {
            throw new RuntimeException('No message found in the request.');
        }

        return $message;
    }

    public static function messageFromRequest(Request $request): ?MessageBag
    {
        $message = $request->attributes->get('data');

        return $message instanceof MessageBag
            ? $message
            : null;
    }

    /**
     * Don't populate objects (put or patch) for event engine messages.
     *
     * @param array<string, mixed> $context
     */
    private function dontPopulateForMessages(array &$context): self
    {
        if (! isset($context[AbstractObjectNormalizer::DEEP_OBJECT_TO_POPULATE])) {
            return $this;
        }

        /** @var HttpOperation|null $operation */
        $operation = $context['operation'] ?? null;

        if ($operation === null) {
            return $this;
        }

        /** @var array<string, mixed>|null $input */
        $input = $operation->getInput();
        /** @var class-string|null $inputClass */
        $inputClass = $input['class'] ?? null;

        if (! $inputClass) {
            return $this;
        }

        $interfaces = class_implements($inputClass);

        if ($interfaces === false) {
            return $this;
        }

        if (! in_array(Command::class, $interfaces) && ! in_array(Query::class, $interfaces)) {
            return $this;
        }

        $context[AbstractObjectNormalizer::DEEP_OBJECT_TO_POPULATE] = false;

        return $this;
    }
}
