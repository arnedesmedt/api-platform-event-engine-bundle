<?php

declare(strict_types=1);

namespace ADS\Bundle\ApiPlatformEventEngineBundle\Command;

use ADS\Bundle\ApiPlatformEventEngineBundle\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function array_keys;
use function array_map;
use function array_merge;
use function reset;

class ApiMappingMessageCommand extends Command
{
    private Config $config;

    public function __construct(Config $config)
    {
        parent::__construct();

        $this->config = $config;
    }

    protected function configure() : void
    {
        $this
            ->setName('api:mapping:message')
            ->setDescription('List the mapping of the api platform calls with the event engine messages.');
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $mapping = $this->config->operationMapping();
        $io = new SymfonyStyle($input, $output);

        $table = $this->mappingToTable($mapping);

        $firstRow = reset($table);

        if ($firstRow === false) {
            return 0;
        }

        $io->table(
            array_map('ucfirst', array_keys($firstRow)),
            $table
        );

        return 0;
    }

    /**
     * @param array<mixed> $mapping
     *
     * @return array<array<string, string>>
     */
    private function mappingToTable(array $mapping) : array
    {
        $table = [];

        foreach ($mapping as $messageClass => $operationData) {
            $table[] = array_merge(
                $operationData,
                ['message' => $messageClass]
            );
        }

        return $table;
    }
}
