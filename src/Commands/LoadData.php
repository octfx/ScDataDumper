<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
        $overwrite = $input->getOption('overwrite');
        $scUnpackedFormat = $input->getOption('scUnpackedFormat');

        $application = $this->getApplication();

        if ($application === null) {
            $io->error('Application not available');

            return Command::FAILURE;
        }

        // Helper to run commands
        $runCommand = static function (string $name, array $args) use ($application, $output) {
            $command = $application->find($name);
            $commandInput = new ArrayInput($args);
            $commandInput->setInteractive(false);

            return $command->run($commandInput, $output);
        };

        // Execute commands in sequence
        $io->section('Generating cache');
        $result = $runCommand('generate:cache', ['path' => $scDataPath]);

        if ($result !== Command::SUCCESS) {
            return $result;
        }

        $io->section('Loading items');
        $itemArgs = [
            'scDataPath' => $scDataPath,
            'jsonOutPath' => $jsonOutPath,
        ];
        if ($overwrite) {
            $itemArgs['--overwrite'] = true;
        }
        if ($scUnpackedFormat) {
            $itemArgs['--scUnpackedFormat'] = true;
        }

        $result = $runCommand('load:items', $itemArgs);

        if ($result !== Command::SUCCESS) {
            return $result;
        }

        $io->section('Loading vehicles');
        $vehicleArgs = [
            'scDataPath' => $scDataPath,
            'jsonOutPath' => $jsonOutPath,
        ];
        if ($overwrite) {
            $vehicleArgs['--overwrite'] = true;
        }
        if ($scUnpackedFormat) {
            $vehicleArgs['--scUnpackedFormat'] = true;
        }

        $result = $runCommand('load:vehicles', $vehicleArgs);

        if ($result !== Command::SUCCESS) {
            return $result;
        }

        $io->section('Loading factions');
        $factionArgs = [
            'scDataPath' => $scDataPath,
            'jsonOutPath' => $jsonOutPath,
        ];
        if ($overwrite) {
            $factionArgs['--overwrite'] = true;
        }
        if ($scUnpackedFormat) {
            $factionArgs['--scUnpackedFormat'] = true;
        }

        $result = $runCommand('load:factions', $factionArgs);

        if ($result !== Command::SUCCESS) {
            return $result;
        }

        $io->section('Loading manufacturers');
        $result = $runCommand('load:manufacturers', [
            'scDataPath' => $scDataPath,
            'jsonOutPath' => $jsonOutPath,
        ]);

        if ($result !== Command::SUCCESS) {
            return $result;
        }

        $io->section('Loading translations');
        $result = $runCommand('load:translations', [
            'scDataPath' => $scDataPath,
            'jsonOutPath' => $jsonOutPath,
        ]);

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
        $this->addOption(
            'overwrite',
            null,
            InputOption::VALUE_NONE,
            'Overwrite existing JSON files in output directory'
        );
        $this->addOption(
            'scUnpackedFormat',
            null,
            InputOption::VALUE_NONE,
            'Export data in SC Unpacked format with Raw entity data'
        );
    }
}
