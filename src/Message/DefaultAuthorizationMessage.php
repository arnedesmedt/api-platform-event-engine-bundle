<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\ApiPlatformEventEngineBundle\Responses\Forbidden;
use ADS\Bundle\ApiPlatformEventEngineBundle\Responses\Unauthorized;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Response;

use function array_filter;
use function preg_match;

trait DefaultAuthorizationMessage
{
    /**
     * @inheritDoc
     */
    public static function __authorizationAttributes(): array
    {
        $reflectionClass = new ReflectionClass(static::class);
        $staticMethods = $reflectionClass->getMethods(ReflectionMethod::IS_STATIC);

        $authorizationMethods = array_filter(
            $staticMethods,
            static function (ReflectionMethod $reflectionMethod) {
                $methodName = $reflectionMethod->getShortName();

                return preg_match('/^__extraAuthorization/', $methodName);
            }
        );

        $authorizationAttributes = [];

        foreach ($authorizationMethods as $authorizationMethod) {
            $authorizationAttributes += $authorizationMethod->invoke(null);
        }

        return $authorizationAttributes;
    }

    /**
     * @return array<int, class-string>
     */
    public static function __extraResponseClassesAuthorization(): array
    {
        return [
            Response::HTTP_UNAUTHORIZED => Unauthorized::class,
            Response::HTTP_FORBIDDEN => Forbidden::class,
        ];
    }
}
