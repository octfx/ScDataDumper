<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use JsonException;
use Octfx\ScDataDumper\DocumentTypes\MissionLocationTemplate;
use Octfx\ScDataDumper\DocumentTypes\Starmap\StarMapObject as StarMapObjectDocument;
use Octfx\ScDataDumper\Formats\ScUnpacked\StarMapObject as StarMapObjectFormat;
use Octfx\ScDataDumper\Formats\ScUnpacked\TradeLocation as TradeLocationFormat;
use Octfx\ScDataDumper\Services\FoundryLookupService;
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
    name: 'load:starmap',
    description: 'Load and dump SC starmap objects and trade locations',
    hidden: false
)]
final class LoadStarmap extends AbstractDataCommand
{
    /**
     * @throws JsonException|ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading starmap objects');

        $this->prepareServices($input, $output);

        $service = ServiceFactory::getFoundryLookupService();
        $filter = $this->normalizeFilter($input->getOption('filter'));
        $overwrite = ($input->getOption('overwrite') ?? false) === true;
        $outDir = rtrim((string) $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);

        $starmapResult = $this->exportStarmap($service, $filter, $overwrite, $outDir, $io);
        if ($starmapResult !== Command::SUCCESS) {
            return $starmapResult;
        }

        $tradeResult = $this->exportTradeLocations($service, $overwrite, $outDir, $io);
        if ($tradeResult !== Command::SUCCESS) {
            return $tradeResult;
        }

        return Command::SUCCESS;
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

    /**
     * @throws JsonException
     */
    private function exportStarmap(
        FoundryLookupService $service,
        ?string $filter,
        bool $overwrite,
        string $outDir,
        SymfonyStyle $io,
    ): int {
        $exports = $this->withLazyReferenceHydration([$service], function () use ($service, $filter, $io): array {
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

            return $exports;
        });

        $outPath = $outDir.DIRECTORY_SEPARATOR.'starmap.json';
        $this->ensureDirectory($outDir);

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

    /**
     * @throws JsonException
     */
    private function exportTradeLocations(
        FoundryLookupService $service,
        bool $overwrite,
        string $outDir,
        SymfonyStyle $io,
    ): int {
        $io->section('Loading trade locations');

        $outPath = $outDir.DIRECTORY_SEPARATOR.'trade_locations.json';

        if (! $overwrite && file_exists($outPath)) {
            $io->success(sprintf('Skipped trade locations export because file already exists: %s', $outPath));

            return Command::SUCCESS;
        }

        $exports = $this->withLazyReferenceHydration([$service], function () use ($service, $io): array {
            $exports = [];
            $io->progressStart($service->countDocumentType('MissionLocationTemplate'));

            foreach ($service->getDocumentType('MissionLocationTemplate', MissionLocationTemplate::class) as $template) {
                $formatted = new TradeLocationFormat($template)->toArray();

                if ($formatted !== null) {
                    $exports[] = $formatted;
                }

                $io->progressAdvance();
            }

            $io->progressFinish();

            return $exports;
        });

        usort($exports, static fn (array $a, array $b): int => [$a['ClassName'], $a['UUID']] <=> [$b['ClassName'], $b['UUID']]);

        $encoded = json_encode($exports, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (! $this->writeJsonFile($outPath, $encoded, $io)) {
            return Command::FAILURE;
        }

        $io->success(sprintf('Saved trade locations export (%s locations | %s)',
            number_format(count($exports)),
            $outPath
        ));

        return Command::SUCCESS;
    }
}
