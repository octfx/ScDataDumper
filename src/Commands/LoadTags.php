<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use JsonException;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'load:tags',
    description: 'Dumps all tags to tags.json',
    hidden: false
)]
class LoadTags extends AbstractDataCommand
{
    /**
     * @throws JsonException|ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading tags');

        $this->prepareServices($input, $output);

        $overwrite = ($input->getOption('overwrite') ?? false) === true;

        $tagService = ServiceFactory::getTagDatabaseService();

        $io->info('Writing tags to tags.json...');

        $filePath = sprintf('%s%stags.json', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);

        if (! $overwrite && file_exists($filePath)) {
            $io->info(sprintf('File %s already exists. Use --overwrite to replace it.', $filePath));

            return Command::SUCCESS;
        }

        try {
            $tags = $tagService->getTagNameMap();
            $json = json_encode($tags, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

            if (! $this->writeJsonFile($filePath, $json, $io)) {
                $io->error('Failed to write tags file');

                return Command::FAILURE;
            }
        } catch (JsonException $e) {
            $io->error(sprintf('Failed to encode tags data: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success(sprintf('Loaded tags (Path: %s)', $input->getArgument('jsonOutPath')));

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp('php cli.php load:tags Path/To/ScDataDir Path/To/JsonOutDir');
        $this->addArgument('scDataPath', InputArgument::REQUIRED, 'Path to unpacked Star Citizen data directory');
        $this->addArgument('jsonOutPath', InputArgument::REQUIRED, 'Output directory for exported JSON files');
        $this->addOption(
            'overwrite',
            null,
            InputOption::VALUE_NONE,
            'Overwrite existing tags.json file'
        );
    }
}
