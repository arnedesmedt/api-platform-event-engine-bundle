<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Documentation;

use ApiPlatform\Core\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\Core\OpenApi\Model\Server;
use ApiPlatform\Core\OpenApi\OpenApi;

use function array_map;

final class OpenApiFactory implements OpenApiFactoryInterface
{
    private OpenApiFactoryInterface $openApiFactory;
    /** @var Server[] */
    private array $servers;
    /** @var array<mixed> */
    private array $tags;

    /**
     * @param array<mixed> $servers
     * @param array<mixed> $tags
     */
    public function __construct(
        OpenApiFactoryInterface $openApiFactory,
        array $servers = [],
        array $tags = []
    ) {
        $this->openApiFactory = $openApiFactory;
        $this->servers = array_map(
            static fn (array $server) => new Server($server['url'], $server['description']),
            $servers
        );
        $this->tags = array_map(
            static fn (string $tag) => ['name' => $tag],
            $tags['order'] ?? []
        );
    }

    /**
     * @param array<mixed> $context
     *
     * @inheritDoc
     */
    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->openApiFactory)($context);

        // todo remove api platform response codes

        return $openApi
            ->withTags($this->tags)
            ->withServers($this->servers);
    }
}
