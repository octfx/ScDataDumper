<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use Illuminate\Support\Arr;
use JsonException;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'load:manufacturers',
    description: 'Load and dump SC Manufacturers',
    hidden: false
)]
class LoadManufacturers extends AbstractDataCommand
{
    /**
     * @throws JsonException|\Symfony\Component\Console\Exception\ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading manufacturers');

        $this->prepareServices($input, $output);

        $service = ServiceFactory::getManufacturerService();

        $io->progressStart($service->count());

        $manufacturers = [];

        try {
            foreach ($service->iterator() as $manufacturer) {
                try {
                    $manufacturerArray = $manufacturer->toArray();
                    $manufacturers[] = $this->buildManufacturerExportEntry($manufacturerArray);
                } catch (RuntimeException $e) {
                    $io->warning(sprintf('Skipped manufacturer: %s', $e->getMessage()));
                }

                $io->progressAdvance();
            }
        } catch (RuntimeException $e) {
            // Ignore
        }

        $io->progressFinish();

        $filePath = sprintf('%s%smanufacturers.json', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);

        try {
            $json = json_encode($manufacturers, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
            if (! $this->writeJsonFile($filePath, $json, $io)) {
                $io->error('Failed to write manufacturers file');

                return Command::FAILURE;
            }
        } catch (JsonException $e) {
            $io->error(sprintf('Failed to encode manufacturers data: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success(sprintf('Manufacturers successfully dumped to %s', $filePath));

        return Command::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $manufacturerArray
     * @return array<string, mixed>
     */
    private function buildManufacturerExportEntry(array $manufacturerArray): array
    {
        $entry = [
            'code' => Arr::get($manufacturerArray, 'Code'),
            'name' => Arr::get($manufacturerArray, 'Localization.Name'),
            'reference' => Arr::get($manufacturerArray, '__ref'),
        ];

        $description = Arr::get($manufacturerArray, 'Localization.Description');
        if (is_string($description)) {
            $description = trim($description);
            if ($description !== '' && ! str_starts_with($description, '@')) {
                $entry['Description'] = $description;
            }
        }

        return $entry;
    }

    protected function configure(): void
    {
        $this->setHelp('php cli.php load:manufacturers Path/To/ScDataDir Path/To/JsonOutDir');
        $this->addArgument('scDataPath', InputArgument::REQUIRED);
        $this->addArgument('jsonOutPath', InputArgument::REQUIRED);
    }
}
