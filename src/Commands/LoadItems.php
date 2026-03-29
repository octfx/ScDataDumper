<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use JsonException;
use Octfx\ScDataDumper\Formats\ScUnpacked\Item;
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
    name: 'load:items',
    description: 'Load and dump SC Items',
    hidden: false
)]
class LoadItems extends Command
{
    /**
     * @throws JsonException|ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading items');

        $this->prepareServices($input, $output);

        $overwrite = ($input->getOption('overwrite') ?? false) === true;
        $io->progressStart($this->getItemExportCount());

        $outDir = sprintf('%s%sitems', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);
        $indexFilePath = sprintf('%s%sitems.json', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);
        $fpsIndexPath = sprintf('%s%sfps-items.json', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);
        $shipIndexPath = sprintf('%s%sship-items.json', $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);

        if (! is_dir($outDir) && ! mkdir($outDir, 0777, true) && ! is_dir($outDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $outDir));
        }

        $indexHandle = fopen($indexFilePath, 'wb');
        $fpsHandle = fopen($fpsIndexPath, 'wb');
        $shipHandle = fopen($shipIndexPath, 'wb');

        if (! $indexHandle || ! $fpsHandle || ! $shipHandle) {
            throw new RuntimeException('Failed to open index output files for writing');
        }

        // Begin JSON array streams
        fwrite($indexHandle, "[\n");
        fwrite($fpsHandle, "[\n");
        fwrite($shipHandle, "[\n");
        $indexFirst = true;
        $fpsFirst = true;
        $shipFirst = true;
        $jsonFlags = JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

        $start = microtime(true);

        $nameFilter = $input->getOption('filter');
        $nameFilter = is_string($nameFilter) && $nameFilter !== '' ? strtolower($nameFilter) : null;

        foreach ($this->iterateItemExports($nameFilter, $input->getOption('typeFilter')) as $itemExport) {
            $io->progressAdvance();

            $fileName = strtolower((string) $itemExport['className']);
            $filePath = sprintf('%s%s%s.json', $outDir, DIRECTORY_SEPARATOR, $fileName);

            $stdItem = $itemExport['formatted'];

            $encodedItem = json_encode($stdItem, $jsonFlags);

            if (! $indexFirst) {
                fwrite($indexHandle, ",\n");
            }

            fwrite($indexHandle, $encodedItem);
            $indexFirst = false;
            $classification = $stdItem['classification'] ?? null;

            if (is_string($classification) && str_starts_with($classification, 'FPS.')) {
                if (! $fpsFirst) {
                    fwrite($fpsHandle, ",\n");
                }
                fwrite($fpsHandle, $encodedItem);
                $fpsFirst = false;
            } elseif (is_string($classification) && str_starts_with($classification, 'Ship.')) {
                if (! $shipFirst) {
                    fwrite($shipHandle, ",\n");
                }
                fwrite($shipHandle, $encodedItem);
                $shipFirst = false;
            }

            if (! $overwrite && file_exists($filePath)) {
                continue;
            }

            try {
                if ($input->getOption('scUnpackedFormat')) {
                    $json = json_encode([
                        'Raw' => [
                            'Entity' => $itemExport['rawEntity'],
                        ],
                        'Item' => $stdItem,
                    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                } else {
                    $json = $itemExport['defaultJson'];
                }

                if (! $this->writeJsonFile($filePath, $json, $io)) {
                    $io->warning(sprintf('Skipping item %s due to write failure', $fileName));

                    continue;
                }
            } catch (JsonException $e) {
                $io->warning(sprintf('Failed to encode JSON for item %s: %s', $fileName, $e->getMessage()));

                continue;
            }
        }

        $end = microtime(true);
        $io->progressFinish();
        $duration = $end - $start;
        $io->success(sprintf('Saved item files (%s | %s )',
            'Took: '.round($duration).' s',
            'Path: '.$input->getArgument('jsonOutPath')
        ));

        fwrite($indexHandle, "\n]\n");
        fwrite($fpsHandle, "\n]\n");
        fwrite($shipHandle, "\n]\n");

        fclose($indexHandle);
        fclose($fpsHandle);
        fclose($shipHandle);

        return Command::SUCCESS;
    }

    protected function prepareServices(InputInterface $input, OutputInterface $output): void
    {
        $cacheCommand = new GenerateCache;
        $cacheCommand->run(new StringInput($input->getArgument('scDataPath')), $output);

        $fac = new ServiceFactory($input->getArgument('scDataPath'));
        $fac->initialize();
    }

    protected function getItemExportCount(): int
    {
        return ServiceFactory::getItemService()->count();
    }

    /**
     * @return iterable<int, array{className: string, formatted: array, rawEntity: array, defaultJson: string}>
     *
     * @throws JsonException
     */
    protected function iterateItemExports(?string $nameFilter, mixed $typeFilter): iterable
    {
        $avoids = $this->buildTypeFilterAvoidList($typeFilter);

        foreach (ServiceFactory::getItemService()->iterator() as $item) {
            $attach = $item->getAttachDef();
            $type = $item->getAttachType();

            if ($attach === null || $type === null || $this->isTypeExcluded($type, $avoids)) {
                continue;
            }

            $className = strtolower($item->getClassName());
            if ($nameFilter !== null && strpos($className, $nameFilter) === false) {
                continue;
            }

            $rawEntity = $item->toArray();
            $formatted = new Item($item)->toArray();

            yield [
                'className' => $item->getClassName(),
                'formatted' => $formatted,
                'rawEntity' => [
                    ...$rawEntity,
                    'ClassName' => $item->getClassName(),
                    '__ref' => $item->getUuid(),
                    '__type' => $item->getAttachType(),
                ],
                'defaultJson' => $item->toJson(),
            ];
        }
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

    /**
     * @return array<int, string>
     */
    private function buildTypeFilterAvoidList(mixed $typeFilter): array
    {
        $defaults = $this->normalizeTypeTokens($this->defaultTypeFilterAvoids());

        if (! is_string($typeFilter)) {
            return $defaults;
        }

        $cliTokens = $this->normalizeTypeTokens(explode(',', $typeFilter));
        if ($cliTokens === []) {
            return $defaults;
        }

        return array_values(array_unique(array_merge($defaults, $cliTokens)));
    }

    private function isTypeExcluded(string $type, array $excludedTypes): bool
    {
        return in_array($this->normalizeTypeToken($type), $excludedTypes, true);
    }

    /**
     * @return array<int, string>
     */
    private function defaultTypeFilterAvoids(): array
    {
        return [
            'undefined',
            'airtrafficcontroller',
            'button',
            'char_body',
            'char_head',
            'char_hair_color',
            'char_head_eyebrow',
            'char_head_eyelash',
            'char_head_eyes',
            'char_head_hair',
            'char_lens',
            'char_skin_color',
            'cloth',
            'debris',
            'noitem_player',
        ];
    }

    /**
     * @param array<int, mixed> $tokens
     * @return array<int, string>
     */
    private function normalizeTypeTokens(array $tokens): array
    {
        $normalized = [];

        foreach ($tokens as $token) {
            if (! is_string($token)) {
                continue;
            }

            $token = $this->normalizeTypeToken($token);
            if ($token === '') {
                continue;
            }

            $normalized[] = $token;
        }

        return $normalized;
    }

    private function normalizeTypeToken(string $token): string
    {
        return strtolower(trim($token));
    }

    protected function configure(): void
    {
        $this->setHelp('php cli.php load:items Path/To/ScDataDir Path/To/JsonOutDir');
        $this->addArgument('scDataPath', InputArgument::REQUIRED, 'Path to unpacked Star Citizen data directory');
        $this->addArgument('jsonOutPath', InputArgument::REQUIRED, 'Output directory for exported JSON files');
        $this->addOption(
            'typeFilter',
            't',
            InputOption::VALUE_OPTIONAL,
            'Comma-separated list of item types to exclude from export (e.g., "helmet,armor")'
        );
        $this->addOption(
            'filter',
            'f',
            InputOption::VALUE_OPTIONAL,
            'Only export items with this substring in their class name (case-insensitive)'
        );
        $this->addOption(
            'overwrite',
            null,
            InputOption::VALUE_NONE,
            'Overwrite existing item JSON files'
        );
        $this->addOption(
            'scUnpackedFormat',
            null,
            InputOption::VALUE_NONE,
            'Export items in SC Unpacked format with Raw entity data and formatted Item data'
        );
    }
}
