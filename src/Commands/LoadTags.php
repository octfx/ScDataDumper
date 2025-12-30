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
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'load:tags',
    description: 'Dumps all tags to tags.json',
    hidden: false
)]
class LoadTags extends Command
{
    /**
     * @throws JsonException|ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheCommand = new GenerateCache;
        $cacheCommand->run(new StringInput($input->getArgument('scDataPath')), $output);

        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading tags');

        $fac = new ServiceFactory($input->getArgument('scDataPath'));
        $fac->initialize();

        $overwrite = ($input->getOption('overwrite') ?? false) === true;

        $tagService = ServiceFactory::getTagDatabaseService();

        $io->info('Writing tags to tags.json...');

        $filePath = sprintf('%s%stags.json', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);

        if (! $overwrite && file_exists($filePath)) {
            $io->info(sprintf('File %s already exists. Use --overwrite to replace it.', $filePath));

            return Command::SUCCESS;
        }

        try {
            $tags = $tagService->getTagMap();
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

    private function writeJsonFile(string $filePath, string $content, SymfonyStyle $io): bool
    {
        try {
            $bytesWritten = file_put_contents($filePath, $content);

            if ($bytesWritten === false) {
                $io->error(sprintf('Failed to write file: %s', $filePath));

                return false;
            }

            return true;
        } catch (Throwable $e) {
            $io->error(sprintf('Error writing %s: %s', $filePath, $e->getMessage()));

            return false;
        }
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
