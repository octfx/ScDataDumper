<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use DOMDocument;
use DOMElement;
use JsonException;
use Octfx\ScDataDumper\DocumentTypes\MissionLocationTemplate;
use Octfx\ScDataDumper\DocumentTypes\Starmap\StarMapObject as StarMapObjectDocument;
use Octfx\ScDataDumper\Formats\ScUnpacked\StarMapObject as StarMapObjectFormat;
use Octfx\ScDataDumper\Formats\ScUnpacked\TradeLocation as TradeLocationFormat;
use Octfx\ScDataDumper\Services\DataDumper\SocpakReader;
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
    description: 'Load and dump SC starmap objects, trade locations, and positions',
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

        $scDataPath = $this->getScDataPath($input);
        $service = ServiceFactory::getFoundryLookupService();
        $filter = $this->normalizeFilter($input->getOption('filter'));
        $overwrite = ($input->getOption('overwrite') ?? false) === true;
        $overwrite = $overwrite || $filter !== null;
        $outDir = rtrim((string) $input->getArgument('jsonOutPath'), DIRECTORY_SEPARATOR);

        $starmapResult = $this->exportStarmap($service, $filter, $overwrite, $outDir, $io);

        if ($starmapResult !== Command::SUCCESS) {
            return $starmapResult;
        }

        $tradeResult = $this->exportTradeLocations($service, $overwrite, $outDir, $io);

        if ($tradeResult !== Command::SUCCESS) {
            return $tradeResult;
        }

        $positionsResult = $this->exportStarmapPositions($scDataPath, $overwrite, $outDir, $io);

        if ($positionsResult !== Command::SUCCESS) {
            return $positionsResult;
        }

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp('php cli.php load:starmap Path/To/ScDataDir Path/To/JsonOutDir');
        $this->addArgument('scDataPath', InputArgument::REQUIRED, 'Path to unpacked Star Citizen data directory');
        $this->addArgument('jsonOutPath', InputArgument::REQUIRED, 'Output directory for the starmap JSON files');
        $this->addOption(
            'overwrite',
            null,
            InputOption::VALUE_NONE,
            'Overwrite existing starmap JSON exports'
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

    /**
     * @throws JsonException
     */
    private function exportStarmapPositions(
        string $scDataPath,
        bool $overwrite,
        string $outDir,
        SymfonyStyle $io,
    ): int {
        $io->section('Loading starmap positions');

        $outPath = $outDir.DIRECTORY_SEPARATOR.'starmap_positions.json';

        if (! $overwrite && file_exists($outPath)) {
            return Command::SUCCESS;
        }

        $this->ensureDirectory($outDir);

        $starmapPath = $outDir.DIRECTORY_SEPARATOR.'starmap.json';
        $starmapLookup = $this->loadStarmapLookup($starmapPath, $io);
        $jpFuelCost = ServiceFactory::getFoundryLookupService()->getJumpPointParams()?->getRequiredFuel() ?? 0;
        $socpakReader = new SocpakReader($scDataPath);
        $systemDir = $scDataPath.DIRECTORY_SEPARATOR.'Data'.DIRECTORY_SEPARATOR.'ObjectContainers'
            .DIRECTORY_SEPARATOR.'PU'.DIRECTORY_SEPARATOR.'system';

        $entities = [];
        $jumpPoints = [];

        foreach ($this->discoverSystems($systemDir, $io) as $systemName => $socpakPath) {
            $io->section(sprintf('Processing system: %s', $systemName));

            $xml = $socpakReader->extractXml($socpakPath);

            if ($xml === null) {
                $io->warning(sprintf('Failed to extract XML from %s', $socpakPath));

                continue;
            }

            $dom = new DOMDocument;
            $dom->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOBLANKS | LIBXML_COMPACT);

            $this->extractEntities(
                $dom->documentElement,
                [0, 0, 0],
                $systemName,
                $starmapLookup,
                $entities,
                $jumpPoints
            );
        }

        $connections = $this->buildConnections($jumpPoints, $jpFuelCost, $io);

        foreach ($jumpPoints as $jumpPoint) {
            $entities[] = $jumpPoint['entity'];
        }

        $encoded = json_encode([
            'entities' => $entities,
            'connections' => $connections,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (! $this->writeJsonFile($outPath, $encoded, $io)) {
            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Saved starmap positions (%d entities, %d connections | %s)',
            count($entities),
            count($connections),
            $outPath
        ));

        return Command::SUCCESS;
    }

    /**
     * Load starmap.json into a UUID-keyed lookup for enrichment.
     *
     * @return array<string, array{name: string, type: string, parent_uuid: string|null}>
     * @throws JsonException
     */
    private function loadStarmapLookup(string $starmapPath, SymfonyStyle $io): array
    {
        if (! file_exists($starmapPath)) {
            $io->error(sprintf('Starmap file not found at %s', $starmapPath));

            return [];
        }

        $data = json_decode(file_get_contents($starmapPath), true, 512, JSON_THROW_ON_ERROR);

        $lookup = [];

        foreach ($data as $entry) {
            $uuid = $entry['UUID'] ?? null;

            if ($uuid === null) {
                continue;
            }

            $lookup[$uuid] = [
                'name' => $entry['Name'] ?? '',
                'type' => $entry['Type']['Name'] ?? ($entry['Type']['Classification'] ?? 'unknown'),
                'parent_uuid' => $entry['ParentUUID'] ?? null,
            ];
        }

        return $lookup;
    }

    /**
     * Discover available systems from the directory structure.
     *
     * @return array<string, string> system name => socpak path
     */
    private function discoverSystems(string $systemDir, SymfonyStyle $io): array
    {
        $systems = [];

        if (! is_dir($systemDir)) {
            return $systems;
        }

        foreach (scandir($systemDir, SCANDIR_SORT_ASCENDING) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $dir = $systemDir.DIRECTORY_SEPARATOR.$entry;
            if (! is_dir($dir)) {
                continue;
            }

            $systemSocpak = $dir.DIRECTORY_SEPARATOR.$entry.'system.socpak';

            if (! file_exists($systemSocpak)) {
                $candidates = glob($dir.DIRECTORY_SEPARATOR.'*system.socpak');
                if ($candidates === [] || $candidates === false) {
                    continue;
                }

                $systemSocpak = $candidates[0];
            }

            $systems[$entry] = $systemSocpak;
        }

        $io->text(sprintf('Discovered %d systems: %s', count($systems), implode(', ', array_keys($systems))));

        return $systems;
    }

    /**
     * Recursively extract entities from the XML hierarchy, accumulating world positions.
     *
     * @param  array<float>  $parentWorld  [x, y, z] accumulated world position
     * @param  array<string, mixed>  $starmapLookup  {name, type, parent_uuid}
     * @param  list<array<string, mixed>>  $entities  Collected entity records
     * @param  list<array<string, mixed>>  $jumpPoints  Collected jump point records
     */
    private function extractEntities(
        DOMElement $node,
        array $parentWorld,
        string $system,
        array $starmapLookup,
        array &$entities,
        array &$jumpPoints,
    ): void {
        $childObjectContainers = $node->getElementsByTagName('ChildObjectContainers');
        if ($childObjectContainers->length === 0) {
            return;
        }

        /** @var DOMElement $container */
        $container = $childObjectContainers->item(0);

        foreach ($container->getElementsByTagName('Child') as $child) {
            if ($child->parentNode !== $container) {
                continue;
            }

            $parts = explode(',', $child->getAttribute('pos'));

            $worldX = $parentWorld[0] + (float) ($parts[0] ?? 0);
            $worldY = $parentWorld[1] + (float) ($parts[1] ?? 0);
            $worldZ = $parentWorld[2] + (float) ($parts[2] ?? 0);

            $starMapUuid = $child->getAttribute('starMapRecord');
            $entityName = $child->getAttribute('entityName');

            if ($starMapUuid === '' && preg_match('/^SD_|^ab_/', $entityName)) {
                $this->extractEntities($child, [$worldX, $worldY, $worldZ], $system, $starmapLookup, $entities, $jumpPoints);

                continue;
            }

            if (stripos($entityName, 'jumppoint') !== false) {
                $jumpPoints[] = [
                    'entity' => $this->buildEntityRecord($starMapUuid, $entityName, $system, $starmapLookup, $worldX, $worldY, $worldZ),
                    'entity_name_raw' => $entityName,
                    'system' => $system,
                    'starMapUuid' => $starMapUuid,
                ];
            } elseif ($starMapUuid !== '') {
                $entities[] = $this->buildEntityRecord($starMapUuid, $entityName, $system, $starmapLookup, $worldX, $worldY, $worldZ);
            }

            $this->extractEntities($child, [$worldX, $worldY, $worldZ], $system, $starmapLookup, $entities, $jumpPoints);
        }
    }

    /**
     * Build a single entity record.
     *
     * @param  array<string, array{name: string, type: string, parent_uuid: string|null}>  $starmapLookup
     * @return array{uuid: string, name: string, type: string, system: string, parent_uuid: string|null, x: float, y: float, z: float}
     */
    private function buildEntityRecord(
        string $starMapUuid,
        string $entityName,
        string $system,
        array $starmapLookup,
        float $x,
        float $y,
        float $z,
    ): array {
        $lookup = $starmapLookup[$starMapUuid] ?? null;

        return [
            'uuid' => $starMapUuid,
            'name' => $lookup['name'] ?? $this->fallbackName($entityName),
            'type' => $lookup['type'] ?? 'unknown',
            'system' => $system,
            'parent_uuid' => $lookup['parent_uuid'] ?? null,
            'x' => $x,
            'y' => $y,
            'z' => $z,
        ];
    }

    /**
     * Minimal fallback when no starmap entry exists (e.g. unpaired JP endpoints).
     */
    private function fallbackName(string $entityName): string
    {
        if (preg_match('/jumppoint[_ ](\w+)_(\w+)/i', $entityName, $matches)) {
            return ucfirst($matches[1]).' - '.ucfirst($matches[2]).' Jump Point';
        }

        return $entityName;
    }

    /**
     * Build jump point connections by pairing endpoints
     *
     * @param  list<array{entity: array, entity_name_raw: string, system: string, starMapUuid: string}>  $jumpPoints
     * @return list<array{entry_uuid: string, exit_uuid: string, entry_system: string, exit_system: string, fuel_cost: int}>
     */
    private function buildConnections(array $jumpPoints, int $fuelCost, SymfonyStyle $io): array
    {
        $connections = [];
        $pairs = [];

        foreach ($jumpPoints as $jumpPoint) {
            $rawName = $jumpPoint['entity_name_raw'];

            if (preg_match('/jumppoint[_ ](\w+)_(\w+)/i', $rawName, $matches)) {
                $key = strtolower($matches[1]).'_'.strtolower($matches[2]);
                $pairs[$key] = $jumpPoint;
            }
        }

        $matched = [];

        foreach ($pairs as $key => $jumpPoint) {
            if (isset($matched[$key]) || ! preg_match('/^(\w+)_(\w+)$/', $key, $matches)) {
                continue;
            }

            $reverseKey = $matches[2].'_'.$matches[1];

            if (isset($pairs[$reverseKey]) && ! isset($matched[$reverseKey])) {
                $entry = $jumpPoint;
                $exit = $pairs[$reverseKey];

                $connections[] = [
                    'entry_uuid' => $entry['starMapUuid'],
                    'exit_uuid' => $exit['starMapUuid'],
                    'entry_system' => $entry['system'],
                    'exit_system' => $exit['system'],
                    'fuel_cost' => $fuelCost,
                ];

                $matched[$key] = true;
                $matched[$reverseKey] = true;
            }
        }

        return $connections;
    }
}
