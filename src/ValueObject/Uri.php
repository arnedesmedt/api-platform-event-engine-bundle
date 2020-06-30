<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\ValueObject;

use ADS\Bundle\EventEngineBundle\Util\ArrayUtil;
use ADS\ValueObjects\Implementation\String\StringValue;

use function array_diff_key;
use function array_flip;
use function array_intersect_key;
use function array_keys;
use function array_map;
use function array_merge;
use function explode;
use function preg_match_all;
use function preg_replace;
use function sprintf;

final class Uri extends StringValue
{
    /**
     * @return array<string>
     */
    public function toPathParameterNames(): array
    {
        return $this->matchParameters($this->toUrlPart());
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    public function toPathParameters(array &$parameters): array
    {
        return $this->toParameters($parameters, $this->toPathParameterNames());
    }

    /**
     * @return array<string>
     */
    public function toQueryParameterNames(): array
    {
        return $this->matchParameters($this->toQueryPart());
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    public function toQueryParameters(array &$parameters): array
    {
        return $this->toParameters($parameters, $this->toQueryParameterNames());
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    public function toAllParameters(array &$parameters): array
    {
        return array_merge(
            $this->toPathParameters($parameters),
            $this->toQueryParameters($parameters)
        );
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string> $parameterNames
     *
     * @return array<string, mixed>
     */
    private function toParameters(array &$parameters, array $parameterNames): array
    {
        /** @var array<string, mixed> $matchingParameters */
        $matchingParameters = array_intersect_key(
            ArrayUtil::toSnakeCasedKeys($parameters),
            array_flip($parameterNames)
        );

        $parameters = array_diff_key($parameters, ArrayUtil::toCamelCasedKeys($matchingParameters));

        return $matchingParameters;
    }

    /**
     * @return array<string>
     */
    private function matchParameters(string $string): array
    {
        preg_match_all('/{([^({})]+)}/', $string, $matches);

        return $matches[1] ?? [];
    }

    public function toQueryPart(): string
    {
        return explode('?', $this->value, 2)[1] ?? '';
    }

    public function toUrlPart(): string
    {
        return explode('?', $this->value, 2)[0] ?? '';
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function replacePathParameters(array &$parameters): self
    {
        return $this->replaceParameters($this->toPathParameters($parameters));
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function replaceQueryParameters(array &$parameters): self
    {
        return $this->replaceParameters($this->toQueryParameters($parameters));
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function replaceAllParameters(array &$parameters): self
    {
        return $this->replaceParameters($this->toAllParameters($parameters));
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function replaceParameters(array $parameters): self
    {
        $patterns = array_map(
            static function (string $pattern) {
                return sprintf('/{%s}/', $pattern);
            },
            array_keys(ArrayUtil::toSnakeCasedKeys($parameters))
        );

        $replacedUri = preg_replace($patterns, $parameters, $this->toString());

        return self::fromString($replacedUri ?? $this->toString());
    }
}
