<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Serializer;

use ADS\Bundle\EventEngineBundle\MetadataExtractor\CommandExtractor;
use ADS\Bundle\EventEngineBundle\MetadataExtractor\QueryExtractor;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Serializer\SerializerContextBuilder;
use ApiPlatform\Serializer\SerializerContextBuilderInterface;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use EventEngine\Messaging\MessageBag;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

final class CustomContextBuilder implements SerializerContextBuilderInterface
{
    public function __construct(
        private SerializerContextBuilder $decorated,
        private readonly QueryExtractor $queryExtractor,
        private readonly CommandExtractor $commandExtractor,
    ) {
    }

    /**
     * @param array<mixed>|null $extractedAttributes
     *
     * @return array<mixed>
     */
    public function createFromRequest(
        Request $request,
        bool $normalization,
        array|null $extractedAttributes = null,
    ): array {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        $this
            ->addMessage($request, $context)
            ->extractQueryParameters($request, $context)
            ->dontPopulateForMessages($context);

        return $context;
    }

    /** @param array<mixed> $context */
    private function addMessage(Request $request, array &$context): self
    {
        $message = self::messageFromRequest($request);

        if ($message === null) {
            return $this;
        }

        $context['message'] = $message;

        return $this;
    }

    /** @param array<mixed> $context */
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

    public static function messageFromRequest(Request $request): MessageBag|null
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
        /** @var class-string<JsonSchemaAwareRecord>|null $inputClass */
        $inputClass = $input['class'] ?? null;

        if (! $inputClass) {
            return $this;
        }

        $reflectionClass = new ReflectionClass($inputClass);

        if (
            ! $this->commandExtractor->isCommandFromReflectionClass($reflectionClass)
            && ! $this->queryExtractor->isQueryFromReflectionClass($reflectionClass)
        ) {
            return $this;
        }

        $context[AbstractObjectNormalizer::DEEP_OBJECT_TO_POPULATE] = false;

        return $this;
    }
}
