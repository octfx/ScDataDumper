<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use JsonException;
use Octfx\ScDataDumper\DocumentTypes\Starmap\StarMapObject as StarMapObjectDocument;
use Octfx\ScDataDumper\Formats\ScUnpacked\StarMapObject as StarMapObjectFormat;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'load:starmap',
    description: 'Load and dump SC starmap objects',
    hidden: false
)]
final class LoadStarmap extends Command
{
    /**
     * @throws JsonException|ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheCommand = new GenerateCache;
        $cacheInput = new ArrayInput(['path' => $input->getArgument('scDataPath')]);
        $cacheCommand->run($cacheInput, $output);

        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading starmap objects');

        $factory = new ServiceFactory($input->getArgument('scDataPath'));
        $factory->initialize();

        $service = ServiceFactory::getFoundryLookupService();
        $filter = $this->normalizeFilter($input->getOption('filter'));

        $exports = [];
        $io->progressStart($service->countDocumentType('StarMapObject'));

        foreach ($service->getDocumentType('StarMapObject', StarMapObjectDocument::class) as $object) {
            if ($filter !== null && ! str_contains(strtolower($object->getClassName()), $filter)) {
                $io->progressAdvance();

                continue;
            }

            $formatted = new StarMapObjectFormat($object)->toArray();

            if ($formatted !== null) {
                $exports[] = $formatted;
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        $outPath = sprintf('%s%sstarmap.json', rtrim((string) $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);
        $outDir = dirname($outPath);
        $overwrite = ($input->getOption('overwrite') ?? false) === true;

        if (! is_dir($outDir) && ! mkdir($outDir, 0777, true) && ! is_dir($outDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $outDir));
        }

        if (! $overwrite && file_exists($outPath)) {
            $io->success(sprintf('Skipped starmap export because file already exists: %s', $outPath));

            return Command::SUCCESS;
        }

        $encoded = json_encode($exports, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (! $this->writeJsonFile($outPath, $encoded, $io)) {
            return Command::FAILURE;
        }

        $io->success(sprintf('Saved starmap export (%s objects | %s)',
            number_format(count($exports)),
            $outPath
        ));

        return Command::SUCCESS;
    }

    private function normalizeFilter(mixed $filter): ?string
    {
        if (! is_string($filter) || $filter === '') {
            return null;
        }

        return strtolower($filter);
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
        $this->setHelp('php cli.php load:starmap Path/To/ScDataDir Path/To/JsonOutDir');
        $this->addArgument('scDataPath', InputArgument::REQUIRED, 'Path to unpacked Star Citizen data directory');
        $this->addArgument('jsonOutPath', InputArgument::REQUIRED, 'Output directory for the starmap JSON file');
        $this->addOption(
            'overwrite',
            null,
            InputOption::VALUE_NONE,
            'Overwrite an existing starmap JSON export'
        );
        $this->addOption(
            'filter',
            'f',
            InputOption::VALUE_OPTIONAL,
            'Only export starmap objects with this substring in their class name (case-insensitive)'
        );
    }
}
