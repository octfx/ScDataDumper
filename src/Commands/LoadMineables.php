<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use JsonException;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestableElement;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestablePreset;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestableProviderPreset;
use Octfx\ScDataDumper\DocumentTypes\Mining\MineableCompositionPart;
use Octfx\ScDataDumper\DocumentTypes\Mining\MiningGlobalParams;
use Octfx\ScDataDumper\DocumentTypes\ResourceType;
use Octfx\ScDataDumper\Formats\ScUnpacked\Mineable as MineableFormat;
use Octfx\ScDataDumper\Formats\ScUnpacked\MineableLocation as MineableLocationFormat;
use Octfx\ScDataDumper\Services\HarvestableProviderStarmapResolver;
use Octfx\ScDataDumper\Services\Mining\MiningQualityRangeResolver;
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
    name: 'load:mineables',
    description: 'Load and dump SC mineables and mining locations',
    hidden: false
)]
final class LoadMineables extends AbstractDataCommand
{
    /**
     * @throws ExceptionInterface|JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading mineables');

        $outputDir = (string) $input->getArgument('jsonOutPath');
        $mineablesDir = sprintf('%s%smineables', rtrim($outputDir, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);

        $this->ensureDirectory($mineablesDir);

        $mineablesPath = sprintf('%s%smineables.json', $mineablesDir, DIRECTORY_SEPARATOR);
        $locationsPath = sprintf('%s%slocations.json', $mineablesDir, DIRECTORY_SEPARATOR);
        $overwrite = ($input->getOption('overwrite') ?? false) === true;

        if (! $overwrite && file_exists($mineablesPath) && file_exists($locationsPath)) {
            $io->success(sprintf('Skipped existing mineables exports at %s', $mineablesDir));

            return Command::SUCCESS;
        }

        $this->prepareServices($input, $output);

        [$mineables, $locations] = $this->withLazyReferenceHydration([
            ServiceFactory::getItemService(),
            ServiceFactory::getFoundryLookupService(),
        ], function () use ($io): array {
            $mineables = $this->buildMineableIndex($io);
            $locations = $this->buildLocationsExport($mineables, $io);

            return [$mineables, $locations];
        });

        $encodedMineables = json_encode($mineables, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $encodedLocations = json_encode($locations, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        if (! $this->writeJsonFile($mineablesPath, $encodedMineables, $io)) {
            return Command::FAILURE;
        }

        if (! $this->writeJsonFile($locationsPath, $encodedLocations, $io)) {
            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Saved mineables exports (%s mineables | %s locations | %s)',
            number_format(count($mineables)),
            number_format(count($locations)),
            $mineablesDir
        ));

        return Command::SUCCESS;
    }

    protected function getItemExportCount(): int
    {
        return ServiceFactory::getItemService()->count();
    }

    /**
     * @return iterable<int, EntityClassDefinition>
     */
    protected function iterateItems(): iterable
    {
        $itemService = ServiceFactory::getItemService();

        foreach ($itemService->iterator() as $item) {
            yield $item;
        }
    }

    protected function configure(): void
    {
        $this->setHelp('php cli.php load:mineables Path/To/ScDataDir Path/To/JsonOutDir [--overwrite]');
        $this->addArgument('scDataPath', InputArgument::REQUIRED, 'Path to unpacked Star Citizen data directory');
        $this->addArgument('jsonOutPath', InputArgument::REQUIRED, 'Output directory for exported JSON files');
        $this->addOption(
            'overwrite',
            null,
            InputOption::VALUE_NONE,
            'Overwrite existing mineable exports'
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildMineableIndex(SymfonyStyle $io): array
    {
        $mineables = [];

        $io->section('Building mineable index');
        $providerCount = ServiceFactory::getFoundryLookupService()->countDocumentType('HarvestableProviderPreset');
        $io->progressStart($this->getItemExportCount() + $providerCount);

        foreach ($this->iterateItems() as $item) {
            $mineable = $this->buildMineableExportEntry($item);
            if ($mineable !== null) {
                $this->mergeMineableEntry($mineables, $mineable);
            }

            $io->progressAdvance();
        }

        foreach (ServiceFactory::getFoundryLookupService()->getDocumentType('HarvestableProviderPreset', HarvestableProviderPreset::class) as $provider) {
            foreach ($this->iterateHarvestableMineableEntries($provider) as $mineable) {
                $this->mergeMineableEntry($mineables, $mineable);
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        $mineables = array_values($mineables);

        usort(
            $mineables,
            static fn (array $left, array $right): int => [$left['name'], $left['key'], $left['uuid']]
                <=> [$right['name'], $right['key'], $right['uuid']]
        );

        return $mineables;
    }

    /**
     * @param list<array<string, mixed>> $mineables
     * @return list<array<string, mixed>>
     */
    private function buildLocationsExport(array $mineables, SymfonyStyle $io): array
    {
        $lookup = ServiceFactory::getFoundryLookupService();
        $resolver = new HarvestableProviderStarmapResolver;
        $qualityResolver = new MiningQualityRangeResolver;
        $mineableIndex = $this->indexMineablesByUuid($mineables);
        $exports = [];

        $io->section('Building mining locations');
        $io->progressStart($lookup->countDocumentType('HarvestableProviderPreset'));

        foreach ($lookup->getDocumentType('HarvestableProviderPreset', HarvestableProviderPreset::class) as $provider) {
            $formatted = new MineableLocationFormat($provider, $resolver, $qualityResolver, $mineableIndex);
            $export = $formatted->toArray();

            if ($export !== null) {
                $exports[] = $export;
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        usort($exports, static fn (array $left, array $right): int => [
            $left['location']['system'],
            $left['location']['name'],
            $left['provider']['name'],
            $left['provider']['uuid'],
        ] <=> [
            $right['location']['system'],
            $right['location']['name'],
            $right['provider']['name'],
            $right['provider']['uuid'],
        ]);

        return $exports;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function indexMineablesByUuid(array $mineables): array
    {
        $index = [];

        foreach ($mineables as $mineable) {
            $uuid = is_string($mineable['uuid'] ?? null) ? strtolower($mineable['uuid']) : null;

            if ($uuid === null || $uuid === '') {
                continue;
            }

            $index[$uuid] = $mineable;
        }

        return $index;
    }

    /**
     * @return array{
     *     uuid: string,
     *     key: string,
     *     name: string,
     *     signature: ?float,
     *     global_params: array<string, mixed>,
     *     composition: array{deposit_name: ?string, minimum_distinct_elements: ?int, parts: list<array<string, mixed>>}
     * }|null
     */
    private function buildMineableExportEntry(EntityClassDefinition $item): ?array
    {
        $mineableParams = $item->getMineableParams();
        $composition = $mineableParams?->getComposition();

        if ($mineableParams === null || $composition === null) {
            return null;
        }

        $attachDef = $item->getAttachDef();
        $mineableData = new MineableFormat($item)->toArray();

        return [
            'uuid' => $item->getUuid(),
            'key' => $item->getClassName(),
            'name' => $this->resolveAttachableName($attachDef) ?? $item->getClassName(),
            'signature' => is_numeric($mineableData['Signature'] ?? null) ? (float) $mineableData['Signature'] : null,
            'global_params' => $this->buildGlobalParams($mineableParams->getGlobalParams()),
            'composition' => [
                'deposit_name' => $this->translate($composition->getDepositName()),
                'minimum_distinct_elements' => $composition->getMinimumDistinctElements(),
                'parts' => array_values(array_map(
                    fn (MineableCompositionPart $part): array => $this->buildCompositionPart($part),
                    $composition->getParts()
                )),
            ],
            'sources' => ['mineable_entity'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function iterateHarvestableMineableEntries(HarvestableProviderPreset $provider): iterable
    {
        foreach ($provider->getHarvestableGroups() as $group) {
            foreach ($group->getHarvestableElements() as $element) {
                $entry = $this->buildHarvestableMineableEntry($element);

                if ($entry !== null) {
                    yield $entry;
                }
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildHarvestableMineableEntry(HarvestableElement $element): ?array
    {
        $harvestablePreset = $this->resolveHarvestablePreset($element);
        $entityClassUuid = $this->resolveEntityClassUuid($element, $harvestablePreset);

        if ($entityClassUuid === null || $entityClassUuid === '') {
            return null;
        }

        $entityClass = $this->resolveEntityClass($element, $entityClassUuid, $harvestablePreset);
        $mineable = $entityClass instanceof EntityClassDefinition ? $this->buildMineableExportEntry($entityClass) : null;

        if ($mineable !== null) {
            $mineable['sources'] = array_values(array_unique([
                ...((array) ($mineable['sources'] ?? [])),
                ...$this->buildHarvestableSources($element, $harvestablePreset),
            ]));

            return $mineable;
        }

        return [
            'uuid' => $entityClassUuid,
            'key' => $entityClass?->getClassName() ?? $harvestablePreset?->getClassName(),
            'name' => $this->resolveHarvestableEntityName($entityClass, $harvestablePreset),
            'signature' => null,
            'global_params' => null,
            'composition' => null,
            'sources' => $this->buildHarvestableSources($element, $harvestablePreset),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $index
     * @param array<string, mixed> $candidate
     */
    private function mergeMineableEntry(array &$index, array $candidate): void
    {
        $uuid = is_string($candidate['uuid'] ?? null) ? strtolower($candidate['uuid']) : null;

        if ($uuid === null || $uuid === '') {
            return;
        }

        $existing = $index[$uuid] ?? null;

        if (! is_array($existing)) {
            $candidate['sources'] = array_values(array_unique(array_filter(
                $candidate['sources'] ?? [],
                static fn (mixed $source): bool => is_string($source) && $source !== ''
            )));
            $index[$uuid] = $candidate;

            return;
        }

        $existing['key'] ??= null;
        $existing['name'] ??= null;
        $existing['signature'] ??= null;
        $existing['global_params'] ??= null;
        $existing['composition'] ??= null;

        foreach (['key', 'name', 'signature', 'global_params', 'composition'] as $field) {
            if (($existing[$field] ?? null) === null && array_key_exists($field, $candidate)) {
                $existing[$field] = $candidate[$field];
            }
        }

        $existing['sources'] = array_values(array_unique(array_filter([
            ...((array) ($existing['sources'] ?? [])),
            ...((array) ($candidate['sources'] ?? [])),
        ], static fn (mixed $source): bool => is_string($source) && $source !== '')));

        $index[$uuid] = $existing;
    }

    /**
     * @return list<string>
     */
    private function buildHarvestableSources(HarvestableElement $element, ?HarvestablePreset $harvestablePreset): array
    {
        $sources = [];

        if ($element->getHarvestableEntityClassReference() !== null) {
            $sources[] = 'harvestable_entity_class';
        }

        if (($harvestablePreset?->getEntityClassReference()) !== null) {
            $sources[] = 'harvestable_preset_entity_class';
        }

        return array_values(array_unique($sources));
    }

    private function resolveEntityClassUuid(HarvestableElement $element, ?HarvestablePreset $harvestablePreset): ?string
    {
        return $element->getHarvestableEntityClassReference()
            ?? $harvestablePreset?->getEntityClassReference()
            ?? $element->getHarvestableEntity()?->getUuid()
            ?? $harvestablePreset?->getEntityClass()?->getUuid();
    }

    private function resolveEntityClass(
        HarvestableElement $element,
        string $entityClassUuid,
        ?HarvestablePreset $harvestablePreset
    ): ?EntityClassDefinition {
        $resolved = ServiceFactory::getItemService()->getByReference($entityClassUuid);

        if ($resolved instanceof EntityClassDefinition) {
            return $resolved;
        }

        return $element->getHarvestableEntity()
            ?? $harvestablePreset?->getEntityClass();
    }

    private function resolveHarvestablePreset(HarvestableElement $element): ?HarvestablePreset
    {
        $reference = $element->getHarvestableReference();

        if ($reference !== null) {
            $resolved = ServiceFactory::getFoundryLookupService()->getHarvestablePresetByReference($reference);

            if ($resolved instanceof HarvestablePreset) {
                return $resolved;
            }
        }

        return $element->getHarvestable();
    }

    private function resolveHarvestableEntityName(
        ?EntityClassDefinition $entityClass,
        ?HarvestablePreset $harvestablePreset
    ): ?string {
        $attachDef = $entityClass?->getAttachDef();

        return $this->resolveAttachableName($attachDef)
            ?? $entityClass?->getClassName()
            ?? $harvestablePreset?->getClassName();
    }

    private function resolveAttachableName(?\Octfx\ScDataDumper\Definitions\Element $attachDef): ?string
    {
        if ($attachDef === null) {
            return null;
        }

        return $this->translate($attachDef->get('Localization/English@Name'))
            ?? $this->translate($attachDef->get('Localization@Name'));
    }

    private function normalizePercentage(float|int|null $value): float|int|null
    {
        if (! is_numeric($value)) {
            return null;
        }

        $normalized = (float) $value;

        if ($normalized > 1.0) {
            $normalized /= 100.0;
        }

        return floor($normalized) === $normalized
            ? (int) $normalized
            : $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGlobalParams(?MiningGlobalParams $globalParams): array
    {
        $wasteResourceType = $this->resolveResourceType(
            $globalParams?->getWasteResourceTypeReference(),
            $globalParams?->getWasteResourceType()
        );

        return [
            'power_capacity_per_mass' => $globalParams?->getPowerCapacityPerMass(),
            'decay_per_mass' => $globalParams?->getDecayPerMass(),
            'optimal_window_size' => $globalParams?->getOptimalWindowSize(),
            'optimal_window_factor' => $globalParams?->getOptimalWindowFactor(),
            'optimal_window_max_size' => $globalParams?->getOptimalWindowMaxSize(),
            'resistance_curve_factor' => $globalParams?->getResistanceCurveFactor(),
            'optimal_window_thinness_curve_factor' => $globalParams?->getOptimalWindowThinnessCurveFactor(),
            'c_scu_per_volume' => $globalParams?->getCScuPerVolume(),
            'default_mass' => $globalParams?->getDefaultMass(),
            'waste_resource_type' => $this->buildResourceTypeSummary($wasteResourceType, false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCompositionPart(MineableCompositionPart $part): array
    {
        $mineableElement = $part->getMineableElement();
        $resourceType = $this->resolveResourceType(
            $mineableElement?->getResourceTypeReference(),
            $mineableElement?->getResourceType()
        );

        return [
            'uuid' => $part->getMineableElementReference(),
            'resource_type' => $this->buildResourceTypeSummary($resourceType),
            'min_percentage' => $part->getMinPercentage(),
            'max_percentage' => $part->getMaxPercentage(),
            'probability' => $this->normalizePercentage($part->getProbability()),
            'quality_scale' => $part->getQualityScale(),
            'curve_exponent' => $part->getCurveExponent(),
            'instability' => $mineableElement?->getInstability(),
            'resistance' => $mineableElement?->getResistance(),
        ];
    }

    private function resolveResourceType(?string $reference, ?ResourceType $resourceType): ?ResourceType
    {
        if ($reference === null) {
            return $resourceType;
        }

        $resolvedResourceType = ServiceFactory::getFoundryLookupService()->getResourceTypeByReference($reference);

        return $resolvedResourceType ?? $resourceType;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildResourceTypeSummary(?ResourceType $resourceType, bool $includeDensity = true): ?array
    {
        if ($resourceType === null) {
            return null;
        }

        $summary = [
            'uuid' => $resourceType->getUuid(),
            'key' => $resourceType->getClassName(),
            'name' => $this->translate($resourceType->getDisplayName()) ?? $resourceType->getClassName(),
        ];

        if ($includeDensity) {
            $summary['density_g_per_cc'] = $resourceType->getDensityGramsPerCubicCentimeter();
        }

        return $summary;
    }

    private function translate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! str_starts_with($value, '@')) {
            return $value;
        }

        try {
            $translated = ServiceFactory::getLocalizationService()->getTranslation($value);
        } catch (RuntimeException) {
            return null;
        }

        if (! is_string($translated) || $translated === '' || $translated === $value) {
            return null;
        }

        return $translated;
    }
}
