<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use JsonException;
use Octfx\ScDataDumper\Formats\ScUnpacked\Ship;
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
    name: 'load:vehicles',
    description: 'Load and dump SC Vehicles',
    hidden: false
)]
class LoadVehicles extends AbstractDataCommand
{
    /**
     * @throws JsonException|ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading vehicles');

        $this->prepareServices($input, $output);

        $overwrite = ($input->getOption('overwrite') ?? false) === true;
        $withRaw = ($input->getOption('with-raw') ?? false) === true;
        $io->progressStart($this->getVehicleExportCount());

        $outDir = sprintf('%s%sships', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);
        $indexFilePath = sprintf('%s%sships.json', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);

        $this->ensureDirectory($outDir);

        $indexHandle = @fopen($indexFilePath, 'wb');
        if (! $indexHandle) {
            throw new RuntimeException('Failed to open ships index file for writing');
        }

        fwrite($indexHandle, "[\n");
        $indexFirst = true;
        $jsonFlags = JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT;

        $start = microtime(true);

        $nameFilter = $this->normalizeFilter($input->getOption('filter'));

        foreach ($this->iterateVehicleExports($nameFilter) as $vehicleExport) {
            $out = [
                'Raw' => [
                    'Entity' => $vehicleExport['entity'],
                ],
                'Vehicle' => $vehicleExport['vehicle'],
                'Loadout' => $vehicleExport['loadout'],
                'ScVehicle' => $vehicleExport['formatted'],
            ];

            $fileName = strtolower((string) $vehicleExport['className']);
            $filePathRaw = sprintf('%s%s%s-raw.json', $outDir, DIRECTORY_SEPARATOR, $fileName);
            $filePathShip = sprintf('%s%s%s.json', $outDir, DIRECTORY_SEPARATOR, $fileName);

            $needsWrite = $overwrite || ! file_exists($filePathShip);

            $encodedShip = json_encode($vehicleExport['formatted'], $jsonFlags);
            if (! $indexFirst) {
                fwrite($indexHandle, ",\n");
            }
            fwrite($indexHandle, $encodedShip);
            $indexFirst = false;
            if (! $needsWrite) {

                $io->progressAdvance();

                continue;
            }

            try {
                if ($withRaw) {
                    $jsonRaw = json_encode($out, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
                    if (! $this->writeJsonFile($filePathRaw, $jsonRaw, $io)) {
                        $io->warning(sprintf('Skipping vehicle %s due to write failure', $fileName));
                        $io->progressAdvance();

                        continue;
                    }
                }

                if (! $this->writeJsonFile($filePathShip, $encodedShip, $io)) {
                    $io->warning(sprintf('Skipping formatted vehicle %s due to write failure', $fileName));
                    $io->progressAdvance();

                    continue;
                }
            } catch (JsonException $e) {
                $io->warning(sprintf('Failed to encode JSON for vehicle %s: %s', $fileName, $e->getMessage()));
                $io->progressAdvance();

                continue;
            }

            $io->progressAdvance();
        }

        $end = microtime(true);
        $io->progressFinish();
        $duration = $end - $start;
        $io->success(sprintf('Saved item files (%s | %s )',
            'Took: '.round($duration).' s',
            'Path: '.$input->getArgument('jsonOutPath')
        ));

        fwrite($indexHandle, "\n]\n");
        fclose($indexHandle);

        return Command::SUCCESS;
    }

    protected function getVehicleExportCount(): int
    {
        return ServiceFactory::getVehicleService()->count();
    }

    /**
     * @return iterable<int, array{className: string, formatted: array, entity: array, vehicle: ?array, loadout: array}>
     */
    protected function iterateVehicleExports(?string $nameFilter): iterable
    {
        foreach (ServiceFactory::getVehicleService()->iterator($nameFilter) as $vehicle) {
            yield [
                'className' => $vehicle->entity->getClassName(),
                'formatted' => new Ship($vehicle)->toArray(),
                'entity' => $vehicle->getVehicleEntityArray(),
                'vehicle' => $vehicle->getVehicleArray(),
                'loadout' => $vehicle->loadout,
            ];
        }
    }

    protected function configure(): void
    {
        $this->setHelp('php cli.php load:vehicles Path/To/ScDataDir Path/To/JsonOutDir');
        $this->addArgument('scDataPath', InputArgument::REQUIRED, 'Path to unpacked Star Citizen data directory');
        $this->addArgument('jsonOutPath', InputArgument::REQUIRED, 'Output directory for exported JSON files');
        $this->addOption(
            'overwrite',
            null,
            InputOption::VALUE_NONE,
            'Overwrite existing vehicle JSON files'
        );
        $this->addOption(
            'filter',
            'f',
            InputOption::VALUE_OPTIONAL,
            'Only export vehicles with this substring in their class name (case-insensitive)'
        );
        $this->addOption(
            'scUnpackedFormat',
            null,
            InputOption::VALUE_NONE,
            'Deprecated: SC Unpacked format is now the default and this option has no effect'
        );
        $this->addOption(
            'with-raw',
            null,
            InputOption::VALUE_NONE,
            'Include raw XML -> JSON dumps for vehicles'
        );
    }
}
