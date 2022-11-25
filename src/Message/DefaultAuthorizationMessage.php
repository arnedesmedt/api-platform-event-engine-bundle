<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Message;

use ADS\Bundle\ApiPlatformEventEngineBundle\Responses\Forbidden;
use ADS\Bundle\ApiPlatformEventEngineBundle\Responses\Unauthorized;
use ADS\Bundle\EventEngineBundle\Command\Command;
use ADS\Bundle\EventEngineBundle\Query\Query;
use ADS\Util\StringUtil;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Response;

use function array_filter;
use function array_merge;
use function array_unique;
use function class_implements;
use function in_array;
use function preg_match;
use function sprintf;
use function strtoupper;

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
            $authorizationAttributes = array_merge($authorizationAttributes, $authorizationMethod->invoke(null));
        }

        return array_unique($authorizationAttributes);
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

    /**
     * @return array<string>
     */
    public static function __extraAuthorizationForResources(): array
    {
        $interfaces = class_implements(static::class);

        if ($interfaces === false) {
            return [];
        }

        $entityName = strtoupper(StringUtil::entityNameFromClassName(static::class));

        return array_filter(
            [
                in_array(Command::class, $interfaces)
                    ? sprintf('ROLE_OAUTH2_%s:WRITE', $entityName)
                    : null,
                in_array(Query::class, $interfaces)
                    ? sprintf('ROLE_OAUTH2_%s:READ', $entityName)
                    : null,
            ]
        );
    }
}
