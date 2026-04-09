<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use Exception;
use Octfx\ScDataDumper\Services\CacheService;
use Octfx\ScDataDumper\Services\DataDumper\Game2ExtractorService;
use Octfx\ScDataDumper\Services\Resource\SocpakMappingGenerator;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'generate:cache',
    description: 'Generate required cache files',
    hidden: false
)]
class GenerateCache extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $cacheFiles = [
            sprintf(
                '%s%sclassToPathMap-%s.json',
                $input->getArgument('path'),
                DIRECTORY_SEPARATOR,
                PHP_OS_FAMILY
            ),
            sprintf(
                '%s%sclassToTypeMap-%s.json',
                $input->getArgument('path'),
                DIRECTORY_SEPARATOR,
                PHP_OS_FAMILY
            ),
            sprintf(
                '%s%sclassToUuidMap-%s.json',
                $input->getArgument('path'),
                DIRECTORY_SEPARATOR,
                PHP_OS_FAMILY
            ),
            sprintf(
                '%s%sentityMetadataMap-%s.json',
                $input->getArgument('path'),
                DIRECTORY_SEPARATOR,
                PHP_OS_FAMILY
            ),
            sprintf(
                '%s%suuidToClassMap-%s.json',
                $input->getArgument('path'),
                DIRECTORY_SEPARATOR,
                PHP_OS_FAMILY
            ),
            sprintf(
                '%s%suuidToPathMap-%s.json',
                $input->getArgument('path'),
                DIRECTORY_SEPARATOR,
                PHP_OS_FAMILY
            ),
        ];

        $allExist = array_reduce($cacheFiles, static fn ($carry, $item) => $carry && file_exists($item), true);

        if ($allExist && ! $input->getOption('overwrite')) {
            $this->generateSocpakMapping($input, $io);

            ServiceFactory::reset();

            return Command::SUCCESS;
        }

        $io->title('[ScDataDumper] Extracting missing Game2 files');
        $service = new Game2ExtractorService($input->getArgument('path'), $io);
        $service->extract();

        $io->title('[ScDataDumper] Generating cache files');

        $service = new CacheService($input->getArgument('path'), $io);

        $io->progressStart();
        $start = microtime(true);
        try {
            $service->makeCacheFiles();
        } catch (Exception $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
        $end = microtime(true);
        $io->progressFinish();
        $duration = $end - $start;
        $io->success(sprintf('Generated cache files (%s | %s )',
            'Took: '.round($duration).' s',
            'Path: '.$input->getArgument('path')
        ));

        $this->generateSocpakMapping($input, $io);

        ServiceFactory::reset();

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp('php cli.php generate:cache Path/To/ScDataDir');
        $this->addArgument('path', InputArgument::REQUIRED, 'Path to unpacked Star Citizen data directory');
        $this->addOption(
            'overwrite',
            null,
            InputOption::VALUE_NONE,
            'Overwrite existing cache files'
        );
    }

    private function generateSocpakMapping(InputInterface $input, SymfonyStyle $io): void
    {
        $mappingPath = sprintf('%s%ssocpak_mappings.json', $input->getArgument('path'), DIRECTORY_SEPARATOR);
        if (file_exists($mappingPath)) {
            return;
        }

        $io->section('Generating socpak mapping');
        $mappingGenerator = new SocpakMappingGenerator($input->getArgument('path'));
        $mappingGenerator->generate();
        $io->success('Generated socpak mapping');
    }
}
