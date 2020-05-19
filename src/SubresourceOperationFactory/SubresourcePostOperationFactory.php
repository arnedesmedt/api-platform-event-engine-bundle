<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\SubresourceOperationFactory;

use ApiPlatform\Core\Operation\Factory\SubresourceOperationFactoryInterface;
use function preg_match;
use function str_replace;

final class SubresourcePostOperationFactory implements SubresourceOperationFactoryInterface
{
    private SubresourceOperationFactoryInterface $decorated;

    public function __construct(SubresourceOperationFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $resourceClass) : array
    {
        $operations = $this->decorated->create($resourceClass);

        foreach ($operations as $operation) {
            if (! preg_match('/get_subresource$/', $operation['route_name'])) {
                continue;
            }

            $postOperation = $operation;

            $postOperation['operation_name'] = str_replace(
                'get_subresource',
                'post_subresoucrce',
                $operation['operation_name']
            );

            $postOperation['route_name'] = str_replace(
                'get_subresource',
                'post_subresoucrce',
                $operation['route_name']
            );

            $postOperation['method'] = 'POST';

            $operations[$postOperation['route_name']] = $postOperation;
        }

        return $operations;
    }
}
