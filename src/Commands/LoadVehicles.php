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
                'Entity' => $vehicle->getVehicleEntityArray(),
                'Vehicle' => $vehicle->getVehicleArray(),
                'Loadout' => $vehicle->loadout,
                'ScVehicle' => $scUnpackedShip,
            ];

            $fileName = strtolower($vehicle->entity->getClassName());
            $filePath = sprintf('%s%s%s-raw.json', $outDir, DIRECTORY_SEPARATOR, $fileName);

            if (! $overwrite && file_exists($filePath)) {
                continue;
            }

            $ref = fopen($filePath, 'wb');
            fwrite($ref, json_encode($out, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            fclose($ref);

            $filePath = sprintf('%s%s%s.json', $outDir, DIRECTORY_SEPARATOR, $fileName);

            $ref = fopen($filePath, 'wb');
            fwrite($ref, json_encode($scUnpackedShip, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            fclose($ref);

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
        $ref = fopen($filePath, 'wb');
        fwrite($ref, json_encode($index, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        fclose($ref);

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp('php cli.php load:vehicles Path/To/ScDataDir');
        $this->addArgument('scDataPath', InputArgument::REQUIRED);
        $this->addArgument('jsonOutPath', InputArgument::REQUIRED);
        $this->addOption('overwrite');
        $this->addOption('scUnpackedFormat');
    }
}
