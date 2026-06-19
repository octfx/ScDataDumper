<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use Illuminate\Support\Arr;
use JsonException;
use Octfx\ScDataDumper\Concerns\NormalizesValues;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
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
    use NormalizesValues;

    /**
     * @throws JsonException|ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading manufacturers');

        $this->prepareServices($input, $output);

        $service = ServiceFactory::getManufacturerService();

        $io->progressStart($service->count());

        $byCanonicalCode = [];

        try {
            foreach ($service->canonicalIterator() as $manufacturer) {
                try {
                    $entry = $this->buildManufacturerExportEntry($manufacturer->toArray());
                } catch (RuntimeException $e) {
                    $io->warning(sprintf('Skipped manufacturer: %s', $e->getMessage()));
                    $io->progressAdvance();
                    continue;
                }

                // data.json is the label authority: its code+name win over the XML
                // values so the export matches item output (items emit data.json codes).
                $xmlCode = (string) ($entry['Code'] ?? '');
                $canonical = $service->resolveCanonicalByNameOrCode($entry['Name'] ?? null, $xmlCode);
                $entry = $this->applyCanonicalOverride($entry, $canonical);

                // Collapse records sharing a canonical code (GHEX+ARCC -> ARCC).
                // Priority: a record that canonicalizes beats one that doesn't;
                $canonicalCode = $canonical['code'] ?? $xmlCode;
                $priority = $canonical === null ? 0 : ($xmlCode === $canonicalCode ? 2 : 1);

                if (! isset($byCanonicalCode[$canonicalCode]) || $priority > $byCanonicalCode[$canonicalCode]['priority']) {
                    $byCanonicalCode[$canonicalCode] = ['entry' => $entry, 'priority' => $priority];
                }

                $io->progressAdvance();
            }
        } catch (RuntimeException $e) {
            // Ignore
        }

        $io->progressFinish();

        $manufacturers = array_column($byCanonicalCode, 'entry');

        usort($manufacturers, static fn (array $a, array $b): int => ($a['Code'] ?? '') <=> ($b['Code'] ?? ''));

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

        return $this->transformArrayKeysToPascalCase($entry);
    }

    /**
     * Override Code/Name from the data.json canonical record.
     * Null canonical (placeholders, shop kiosks) leaves the entry as-is.
     *
     * @param  array<string, mixed>  $entry
     * @param  array{code: string, name: string, uuid: ?string}|null  $canonical
     * @return array<string, mixed>
     */
    private function applyCanonicalOverride(array $entry, ?array $canonical): array
    {
        if ($canonical !== null) {
            if (($canonical['code'] ?? '') !== '') {
                $entry['Code'] = $canonical['code'];
            }

            if (($canonical['name'] ?? '') !== '') {
                $entry['Name'] = $canonical['name'];
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
