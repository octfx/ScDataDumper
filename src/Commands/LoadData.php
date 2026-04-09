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

        foreach ($this->subcommands() as $definition) {
            $io->section($definition['label']);

            $result = $this->runSubcommand(
                $application,
                $output,
                $definition['name'],
                $this->buildSubcommandArguments($definition['name'], $definition['options'], $scDataPath, $jsonOutPath, $overwrite, $scUnpackedFormat)
            );

            if ($result !== Command::SUCCESS) {
                return $result;
            }
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
            'Deprecated: SC Unpacked format is now the default and this option has no effect'
        );
    }

    /**
     * @return list<array{name: string, label: string, options: list<string>}>
     */
    private function subcommands(): array
    {
        return [
            ['name' => 'generate:cache', 'label' => 'Generating cache', 'options' => []],
            ['name' => 'load:items', 'label' => 'Loading items', 'options' => ['overwrite', 'scUnpackedFormat']],
            ['name' => 'load:blueprints', 'label' => 'Loading blueprints', 'options' => ['overwrite', 'scUnpackedFormat']],
            ['name' => 'load:resource-types', 'label' => 'Loading resource types', 'options' => ['overwrite']],
            ['name' => 'load:vehicles', 'label' => 'Loading vehicles', 'options' => ['overwrite', 'scUnpackedFormat']],
            ['name' => 'load:factions', 'label' => 'Loading factions', 'options' => ['overwrite', 'scUnpackedFormat']],
            ['name' => 'load:starmap', 'label' => 'Loading starmap', 'options' => ['overwrite']],
            ['name' => 'load:resources', 'label' => 'Loading resources', 'options' => ['overwrite']],
            ['name' => 'load:manufacturers', 'label' => 'Loading manufacturers', 'options' => []],
            ['name' => 'load:translations', 'label' => 'Loading translations', 'options' => []],
            ['name' => 'load:tags', 'label' => 'Loading tags', 'options' => ['overwrite']],
        ];
    }

    /**
     * @param  list<string>  $options
     * @return array<string, bool|string>
     */
    private function buildSubcommandArguments(
        string $commandName,
        array $options,
        string $scDataPath,
        string $jsonOutPath,
        bool $overwrite,
        bool $scUnpackedFormat,
    ): array {
        $arguments = $commandName === 'generate:cache'
            ? ['path' => $scDataPath]
            : [
                'scDataPath' => $scDataPath,
                'jsonOutPath' => $jsonOutPath,
            ];

        $optionValues = [
            'overwrite' => $overwrite,
            'scUnpackedFormat' => $scUnpackedFormat,
        ];

        foreach ($options as $option) {
            if (($optionValues[$option] ?? false) === true) {
                $arguments['--'.$option] = true;
            }
        }

        return $arguments;
    }

    /**
     * @param  array<string, bool|string>  $arguments
     */
    private function runSubcommand(
        \Symfony\Component\Console\Application $application,
        OutputInterface $output,
        string $name,
        array $arguments,
    ): int {
        $command = $application->find($name);
        $commandInput = new ArrayInput($arguments);
        $commandInput->setInteractive(false);

        return $command->run($commandInput, $output);
    }
}
