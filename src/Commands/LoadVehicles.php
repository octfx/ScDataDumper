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
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'load:vehicles',
    description: 'Load and dump SC Vehicles',
    hidden: false
)]
class LoadVehicles extends Command
{
    /**
     * @throws JsonException|ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheCommand = new GenerateCache;
        $cacheCommand->run(new StringInput($input->getArgument('scDataPath')), $output);

        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading vehicles');

        $fac = new ServiceFactory($input->getArgument('scDataPath'));
        $fac->initialize();

        $overwrite = ($input->getOption('overwrite') ?? false) === true;

        $service = ServiceFactory::getVehicleService();

        $io->progressStart($service->count());

        $outDir = sprintf('%s%sships', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);

        if (! is_dir($outDir) && ! mkdir($outDir, 0777, true) && ! is_dir($outDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $outDir));
        }

        $start = microtime(true);

        $index = [];

        foreach ($service->iterator() as $vehicle) {
            $scUnpackedShip = (new Ship($vehicle))->toArray();

            $index[] = $scUnpackedShip;

            $out = [
                'Raw' => [
                    'Entity' => $vehicle->getVehicleEntityArray(),
                ],
                'Vehicle' => $vehicle->getVehicleArray(),
                'Loadout' => $vehicle->loadout,
                'ScVehicle' => $scUnpackedShip,
            ];

            $fileName = strtolower($vehicle->entity->getClassName());
            $filePath = sprintf('%s%s%s-raw.json', $outDir, DIRECTORY_SEPARATOR, $fileName);

            if (! $overwrite && file_exists($filePath)) {
                $io->progressAdvance();

                continue;
            }

            try {
                $jsonRaw = json_encode($out, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
                if (! $this->writeJsonFile($filePath, $jsonRaw, $io)) {
                    $io->warning(sprintf('Skipping vehicle %s due to write failure', $fileName));
                    $io->progressAdvance();

                    continue;
                }

                $filePath = sprintf('%s%s%s.json', $outDir, DIRECTORY_SEPARATOR, $fileName);
                $jsonShip = json_encode($scUnpackedShip, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
                if (! $this->writeJsonFile($filePath, $jsonShip, $io)) {
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

        $filePath = sprintf('%s%sships.json', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);

        try {
            $json = json_encode($index, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
            if (! $this->writeJsonFile($filePath, $json, $io)) {
                $io->error('Failed to write ships index file');

                return Command::FAILURE;
            }
        } catch (JsonException $e) {
            $io->error(sprintf('Failed to encode ships index: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Safely write JSON content to file
     */
    private function writeJsonFile(string $filePath, string $content, SymfonyStyle $io): bool
    {
        try {
            $bytesWritten = file_put_contents($filePath, $content);

            if ($bytesWritten === false) {
                $io->error(sprintf('Failed to write file: %s', $filePath));

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $io->error(sprintf('Error writing %s: %s', $filePath, $e->getMessage()));

            return false;
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
            'scUnpackedFormat',
            null,
            InputOption::VALUE_NONE,
            'Export vehicles in SC Unpacked format (currently has no effect)'
        );
    }
}
