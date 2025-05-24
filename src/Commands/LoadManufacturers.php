<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use JsonException;
use Octfx\ScDataDumper\Helper\Arr;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'load:manufacturers',
    description: 'Load and dump SC Manufacturers',
    hidden: false
)]
class LoadManufacturers extends Command
{
    /**
     * @throws JsonException|\Symfony\Component\Console\Exception\ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheCommand = new GenerateCache;
        $cacheCommand->run(new StringInput($input->getArgument('scDataPath')), $output);

        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading manufacturers');

        $fac = new ServiceFactory($input->getArgument('scDataPath'));
        $fac->initialize();

        $service = ServiceFactory::getManufacturerService();

        $io->progressStart($service->count());

        $manufacturers = [];

        try {
            foreach ($service->iterator() as $manufacturer) {
                $manufacturer = $manufacturer->toArray();
                $manufacturers[] = [
                    'code' => Arr::get($manufacturer, 'Code'),
                    'name' => Arr::get($manufacturer, 'Localization.Name'),
                    // 'description' => Arr::get($manufacturer, 'Localization.Description'),
                    'reference' => Arr::get($manufacturer, '__ref'),
                ];

                $io->progressAdvance();
            }
        } catch (RuntimeException $e) {
            // Ignore
        }

        $io->progressFinish();

        $filePath = sprintf('%s%smanufacturers.json', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);
        $ref = fopen($filePath, 'wb');
        fwrite($ref, json_encode($manufacturers, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        fclose($ref);

        $io->success(sprintf('Manufacturers successfully dumped to %s', $filePath));

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp('php cli.php load:manufacturers Path/To/ScDataDir Path/To/JsonOutDir');
        $this->addArgument('scDataPath', InputArgument::REQUIRED);
        $this->addArgument('jsonOutPath', InputArgument::REQUIRED);
    }
}
