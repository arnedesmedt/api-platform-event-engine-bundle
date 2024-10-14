<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Controller;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\State\Util\OperationRequestInitiatorTrait;
use ApiPlatform\Symfony\Util\RequestAttributesExtractor;
use ApiPlatform\Symfony\Validator\Exception\ConstraintViolationListAwareExceptionInterface;
use ApiPlatform\Util\ErrorFormatGuesser;
// phpcs:ignore Generic.Files.LineLength.TooLong
use ApiPlatform\Validator\Exception\ConstraintViolationListAwareExceptionInterface as ApiPlatformConstraintViolationListAwareExceptionInterface;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

use function array_merge;
use function is_a;
use function method_exists;
use function sprintf;

/**
 * @deprecated since API Platform 3 and Error resource is used {@see ApiPlatform\Symfony\EventListener\ErrorListener}
 *
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.ElseExpression)
 */
final class ExceptionAction
{
    use OperationRequestInitiatorTrait;

    /**
     * @param array<string, string> $errorFormats      A list of enabled error formats
     * @param array<string, int>    $exceptionToStatus A list of exceptions mapped to their HTTP status code
     */
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly array $errorFormats,
        private readonly array $exceptionToStatus = [],
        ResourceMetadataCollectionFactoryInterface|null $resourceMetadataCollectionFactory = null,
    ) {
        $this->resourceMetadataCollectionFactory = $resourceMetadataCollectionFactory;
    }

    /**
     * Converts an exception to a JSON response.
     */
    public function __invoke(FlattenException|HttpExceptionInterface $exception, Request $request): Response
    {
        $operation = $this->initializeOperation($request);
        $exceptionClass = $exception instanceof FlattenException ? $exception->getClass() : $exception::class;
        $statusCode = $exception->getStatusCode();

        $exceptionToStatus = array_merge(
            $this->exceptionToStatus,
            $operation ? $operation->getExceptionToStatus() ?? [] : $this->getOperationExceptionToStatus($request),
        );

        foreach ($exceptionToStatus as $class => $status) {
            if (is_a($exceptionClass, $class, true)) {
                $statusCode = $status;

                break;
            }
        }

        $headers = $exception->getHeaders();
        $format = ErrorFormatGuesser::guessErrorFormat($request, $this->errorFormats);
        $headers['Content-Type'] = sprintf('%s; charset=utf-8', $format['value'][0]);
        $headers['X-Content-Type-Options'] = 'nosniff';
        $headers['X-Frame-Options'] = 'deny';

        $context = [
            'statusCode' => $statusCode,
            'rfc_7807_compliant_errors' => $operation?->getExtraProperties()['rfc_7807_compliant_errors'] ?? false,
        ];
        /** @var object $error */
        $error = $request->attributes->get('exception') ?? $exception;
        if (
            $error instanceof ConstraintViolationListAwareExceptionInterface
            || $error instanceof ApiPlatformConstraintViolationListAwareExceptionInterface
        ) {
            $error = $error->getConstraintViolationList();
        } elseif (
            method_exists($error, 'getViolations')
            && $error->getViolations() instanceof ConstraintViolationListInterface
        ) {
            $error = $error->getViolations();
        } else {
            $error = $exception;
        }

        $serializerFormat = $format['key'];
        if ($serializerFormat === 'json' && $format['value'][0] === 'application/problem+json') {
            $serializerFormat = 'jsonproblem';
        }

        return new Response($this->serializer->serialize($error, $serializerFormat, $context), $statusCode, $headers);
    }

    /** @return array<mixed> */
    private function getOperationExceptionToStatus(Request $request): array
    {
        $attributes = RequestAttributesExtractor::extractAttributes($request);

        if ($attributes === []) {
            return [];
        }

        $resourceMetadataCollection = $this->resourceMetadataCollectionFactory?->create($attributes['resource_class']);
        /** @var HttpOperation $operation */
        $operation = $resourceMetadataCollection?->getOperation($attributes['operation_name'] ?? null);
        $exceptionToStatus = [$operation->getExceptionToStatus() ?: []];

        foreach ($resourceMetadataCollection ?? [] as $resourceMetadata) {
            /** @var ApiResource $resourceMetadata */
            $exceptionToStatus[] = $resourceMetadata->getExceptionToStatus() ?: [];
        }

        return array_merge(...$exceptionToStatus);
    }
}
