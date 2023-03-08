<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Documentation;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\Model\Server;
use ApiPlatform\OpenApi\OpenApi;

use function array_map;
use function ceil;
use function floor;
use function rtrim;
use function similar_text;
use function str_starts_with;
use function substr;
use function usort;

final class OpenApiFactory implements OpenApiFactoryInterface
{
    /**
     * @var Server[]
     * @readonly
     */
    private array $servers;

    /**
     * @var array<mixed>
     * @readonly
     */
    private array $tags;

    /**
     * @param array<array<string, string>> $servers
     * @param array<string, array<string>> $tags
     */
    public function __construct(
        private OpenApiFactoryInterface $openApiFactory,
        array $servers = [],
        array $tags = [],
    ) {
        if (isset($_SERVER['HTTP_HOST'])) {
            usort(
                $servers,
                static function (array $server1, array $server2) {
                    $percentage1 = $percentage2 = 0.0;
                    similar_text($server2['url'], $_SERVER['HTTP_HOST'], $percentage2);
                    similar_text($server1['url'], $_SERVER['HTTP_HOST'], $percentage1);

                    $diff = ($percentage2 - $percentage1) / 100;

                    return (int) ($diff > 0 ? ceil($diff) : floor($diff));
                },
            );
        }

        $this->servers = array_map(
            static fn (array $server) => new Server(rtrim($server['url'], '/') . '/api/', $server['description']),
            $servers,
        );

        $this->tags = array_map(
            static fn (string $tag) => ['name' => $tag],
            $tags['order'] ?? [],
        );
    }

    /**
     * {@inheritdoc}
     *
     * @param array<string, mixed> $context
     */
    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->openApiFactory)($context);

        $paths = new Paths();

        foreach ($openApi->getPaths()->getPaths() as $path => $operation) {
            if (str_starts_with($path, '/api')) {
                $path = substr($path, 4);
            }

            $paths->addPath($path, $operation);
        }

        return $openApi
            ->withPaths($paths)
            ->withServers($this->servers)
            ->withTags($this->tags);
    }
}
