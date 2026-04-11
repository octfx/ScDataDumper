<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use JsonException;
use Octfx\ScDataDumper\Formats\ScUnpacked\Blueprint;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'load:blueprints',
    description: 'Load and dump SC crafting blueprints',
    hidden: false
)]
class LoadBlueprints extends AbstractDataCommand
{
    /**
     * @throws JsonException|ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading blueprints');

        $this->prepareServices($input, $output);

        $overwrite = ($input->getOption('overwrite') ?? false) === true;
        $io->progressStart($this->getBlueprintExportCount());

        $outDir = sprintf('%s%sblueprints', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);
        $indexFilePath = sprintf('%s%sblueprints.json', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);

        $this->ensureDirectory($outDir);

        $indexHandle = @fopen($indexFilePath, 'wb');
        if (! $indexHandle) {
            throw new RuntimeException('Failed to open blueprints index file for writing');
        }

        fwrite($indexHandle, "[\n");
        $indexFirst = true;
        $jsonFlags = JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

        $start = microtime(true);

        $nameFilter = $input->getOption('filter');
        $nameFilter = is_string($nameFilter) && $nameFilter !== '' ? strtolower($nameFilter) : null;

        $this->withLazyReferenceHydration([
            ServiceFactory::getItemService(),
            ServiceFactory::getFoundryLookupService(),
            ServiceFactory::getBlueprintService(),
        ], function () use (
            $nameFilter,
            $overwrite,
            $outDir,
            $io,
            $indexHandle,
            &$indexFirst,
            $jsonFlags
        ): void {
            foreach ($this->iterateBlueprintExports($nameFilter) as $blueprintExport) {
                $formatted = $blueprintExport['formatted'];
                if ($formatted === null) {
                    $io->progressAdvance();

                    continue;
                }

                $fileName = strtolower((string) $blueprintExport['className']);
                $filePath = sprintf('%s%s%s.json', $outDir, DIRECTORY_SEPARATOR, $fileName);

                $encodedBlueprint = json_encode($formatted, JSON_THROW_ON_ERROR | $jsonFlags);
                if (! $indexFirst) {
                    fwrite($indexHandle, ",\n");
                }
                fwrite($indexHandle, $encodedBlueprint);
                $indexFirst = false;

                if (! $overwrite && file_exists($filePath)) {
                    $io->progressAdvance();

                    continue;
                }

                try {
                    $json = json_encode([
                        'Raw' => [
                            'Blueprint' => $blueprintExport['rawBlueprint'],
                        ],
                        'Blueprint' => $formatted,
                    ], JSON_THROW_ON_ERROR | $jsonFlags);

                    if (! $this->writeJsonFile($filePath, $json, $io)) {
                        $io->warning(sprintf('Skipping blueprint %s due to write failure', $fileName));
                        $io->progressAdvance();

                        continue;
                    }
                } catch (JsonException $e) {
                    $io->warning(sprintf('Failed to encode JSON for blueprint %s: %s', $fileName, $e->getMessage()));
                    $io->progressAdvance();

                    continue;
                }

                $io->progressAdvance();
            }
        });

        $end = microtime(true);
        $io->progressFinish();
        $duration = $end - $start;
        $io->success(sprintf('Saved blueprint files (%s | %s )',
            'Took: '.round($duration).' s',
            'Path: '.$input->getArgument('jsonOutPath')
        ));

        fwrite($indexHandle, "\n]\n");
        fclose($indexHandle);

        return Command::SUCCESS;
    }

    protected function getBlueprintExportCount(): int
    {
        return ServiceFactory::getBlueprintService()->count();
    }

    /**
     * @return iterable<int, array{className: string, formatted: ?array, rawBlueprint: array, defaultJson: string}>
     *
     * @throws JsonException
     */
    protected function iterateBlueprintExports(?string $nameFilter): iterable
    {
        foreach (ServiceFactory::getBlueprintService()->iterator() as $blueprint) {
            $className = $blueprint->getClassName();

            if ($nameFilter !== null && ! str_contains(strtolower($className), $nameFilter)) {
                continue;
            }

            yield [
                'className' => $className,
                'formatted' => new Blueprint($blueprint)->toArray(),
                'rawBlueprint' => $blueprint->toArray(),
                'defaultJson' => $blueprint->toJson(),
            ];
        }
    }

    protected function configure(): void
    {
        $this->setHelp('php cli.php load:blueprints Path/To/ScDataDir Path/To/JsonOutDir');
        $this->addArgument('scDataPath', InputArgument::REQUIRED, 'Path to unpacked Star Citizen data directory');
        $this->addArgument('jsonOutPath', InputArgument::REQUIRED, 'Output directory for exported JSON files');
        $this->addOption(
            'overwrite',
            null,
            InputOption::VALUE_NONE,
            'Overwrite existing blueprint JSON files'
        );
        $this->addOption(
            'filter',
            'f',
            InputOption::VALUE_OPTIONAL,
            'Only export blueprints with this substring in their class name (case-insensitive)'
        );
        $this->addOption(
            'scUnpackedFormat',
            null,
            InputOption::VALUE_NONE,
            'Deprecated: SC Unpacked format is now the default and this option has no effect'
        );
    }
}
