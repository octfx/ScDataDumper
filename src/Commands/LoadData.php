<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'load:data',
    description: 'Load and dump all SC data by calling multiple commands in sequence',
    hidden: false
)]
class LoadData extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading all data');

        $scDataPath = $input->getArgument('scDataPath');
        $jsonOutPath = $input->getArgument('jsonOutPath');
        $overwrite = $input->getOption('overwrite') ?? false;
        $scUnpackedFormat = $input->getOption('scUnpackedFormat') ?? false;

        // Create command instances
        $generateCacheCommand = new GenerateCache;
        $loadItemsCommand = new LoadItems;
        $loadVehiclesCommand = new LoadVehicles;
        $loadFactionsCommand = new LoadFactions;
        $loadManufacturersCommand = new LoadManufacturers;
        $loadTranslationsCommand = new LoadTranslations;

        // Execute commands in sequence
        $io->section('Generating cache');
        $result = $generateCacheCommand->run(
            new StringInput($scDataPath),
            $output
        );

        if ($result !== Command::SUCCESS) {
            return $result;
        }

        $io->section('Loading items');
        $itemArgs = sprintf(
            '%s %s %s %s',
            $scDataPath,
            $jsonOutPath,
            $overwrite ? '--overwrite' : '',
            $scUnpackedFormat ? '--scUnpackedFormat' : ''
        );
        $result = $loadItemsCommand->run(
            new StringInput($itemArgs),
            $output
        );

        if ($result !== Command::SUCCESS) {
            return $result;
        }

        $io->section('Loading vehicles');
        $vehicleArgs = sprintf(
            '%s %s %s %s',
            $scDataPath,
            $jsonOutPath,
            $overwrite ? '--overwrite' : '',
            $scUnpackedFormat ? '--scUnpackedFormat' : ''
        );
        $result = $loadVehiclesCommand->run(
            new StringInput($vehicleArgs),
            $output
        );

        if ($result !== Command::SUCCESS) {
            return $result;
        }

        $io->section('Loading factions');
        $factionArgs = sprintf(
            '%s %s %s %s',
            $scDataPath,
            $jsonOutPath,
            $overwrite ? '--overwrite' : '',
            $scUnpackedFormat ? '--scUnpackedFormat' : ''
        );
        $result = $loadFactionsCommand->run(
            new StringInput($factionArgs),
            $output
        );

        if ($result !== Command::SUCCESS) {
            return $result;
        }

        $io->section('Loading manufacturers');
        $manufacturerArgs = sprintf(
            '%s %s',
            $scDataPath,
            $jsonOutPath,
        );
        $result = $loadManufacturersCommand->run(
            new StringInput($manufacturerArgs),
            $output
        );

        if ($result !== Command::SUCCESS) {
            return $result;
        }

        $io->section('Loading translations');
        $translationArgs = sprintf(
            '%s %s',
            $scDataPath,
            $jsonOutPath,
        );
        $result = $loadTranslationsCommand->run(
            new StringInput($translationArgs),
            $output
        );

        if ($result !== Command::SUCCESS) {
            return $result;
        }

        $io->success('All data loaded successfully');

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp('php cli.php load:data Path/To/ScDataDir Path/To/JsonOutput [--overwrite] [--scUnpackedFormat]');
        $this->addArgument('scDataPath', InputArgument::REQUIRED, 'Path to Star Citizen data directory');
        $this->addArgument('jsonOutPath', InputArgument::REQUIRED, 'Path where JSON files will be saved');
        $this->addOption('overwrite', null, null, 'Overwrite existing files');
        $this->addOption('scUnpackedFormat', null, null, 'Use SC Unpacked format');
    }
}
