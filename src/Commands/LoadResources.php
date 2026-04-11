<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands;

use JsonException;
use Octfx\ScDataDumper\Concerns\NormalizesValues;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestableElement;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestablePreset;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestableProviderPreset;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\SubHarvestableMultiConfigRecord;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\TaggedSubHarvestableConfig;
use Octfx\ScDataDumper\Formats\ScUnpacked\ResourceLocation as ResourceLocationFormat;
use Octfx\ScDataDumper\Services\Resource\CaveHarvestableResolver;
use Octfx\ScDataDumper\Services\Resource\HarvestableProviderStarmapResolver;
use Octfx\ScDataDumper\Services\Resource\QualityRangeResolver;
use Octfx\ScDataDumper\Services\Resource\ResourceIndexBuilder;
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
    name: 'load:resources',
    description: 'Load and dump SC resources (mineables, harvestables, salvage) and mining locations',
    hidden: false
)]
final class LoadResources extends AbstractDataCommand
{
    use NormalizesValues;

    private const array MERGEABLE_FIELDS = [
        'key',
        'name',
        'signature',
        'global_params',
        'composition',
        'harvestable_uuid',
        'harvestable_key',
        'respawn_in_slot_time',
        'despawn_time_seconds',
        'additional_wait_for_nearby_players_seconds',
        'parts',
        'tier',
    ];

    /**
     * @throws ExceptionInterface|JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('[ScDataDumper] Loading resources');

        $outputDir = (string) $input->getArgument('jsonOutPath');
        $resourcesDir = sprintf('%s%sresources', rtrim($outputDir, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);

        $this->ensureDirectory($resourcesDir);

        $resourcesPath = sprintf('%s%sresources.json', $resourcesDir, DIRECTORY_SEPARATOR);
        $locationsPath = sprintf('%s%slocations.json', $resourcesDir, DIRECTORY_SEPARATOR);
        $overwrite = ($input->getOption('overwrite') ?? false) === true;

        if (! $overwrite && file_exists($resourcesPath) && file_exists($locationsPath)) {
            $io->success(sprintf('Skipped existing resource exports at %s', $resourcesDir));

            return Command::SUCCESS;
        }

        $this->prepareServices($input, $output);

        [$resources, $locations] = $this->withLazyReferenceHydration([
            ServiceFactory::getItemService(),
            ServiceFactory::getFoundryLookupService(),
        ], function () use ($io): array {
            $resources = $this->buildResourceIndex($io);
            $locations = $this->buildLocationsExport($resources, $io);

            return [$resources, $locations];
        });

        $resources = array_values(array_map(
            fn (array $resource): array => $this->transformArrayKeysToPascalCase($this->removeNullValues($resource)),
            $resources
        ));

        $encodedResources = json_encode($resources, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $encodedLocations = json_encode($locations, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        if (! $this->writeJsonFile($resourcesPath, $encodedResources, $io)) {
            return Command::FAILURE;
        }

        if (! $this->writeJsonFile($locationsPath, $encodedLocations, $io)) {
            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Saved resource exports (%s resources | %s locations | %s)',
            number_format(count($resources)),
            number_format(count($locations)),
            $resourcesDir
        ));

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp('php cli.php load:resources Path/To/ScDataDir Path/To/JsonOutDir [--overwrite]');
        $this->addArgument('scDataPath', InputArgument::REQUIRED, 'Path to unpacked Star Citizen data directory');
        $this->addArgument('jsonOutPath', InputArgument::REQUIRED, 'Output directory for exported JSON files');
        $this->addOption(
            'overwrite',
            null,
            InputOption::VALUE_NONE,
            'Overwrite existing resource exports'
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildResourceIndex(SymfonyStyle $io): array
    {
        $resources = [];
        $entryBuilder = new ResourceIndexBuilder(
            ServiceFactory::getLocalizationService(),
            ServiceFactory::getFoundryLookupService(),
            ServiceFactory::getItemService(),
        );

        $io->section('Building resource index');
        $providerCount = ServiceFactory::getFoundryLookupService()->countDocumentType('HarvestableProviderPreset');
        $caveConfigCount = ServiceFactory::getFoundryLookupService()->countDocumentType('SubHarvestableMultiConfigRecord');
        $io->progressStart(ServiceFactory::getItemService()->count() + $providerCount + $caveConfigCount);

        [$providerEntries, $providerTargets, $providerPresets] = $this->collectProviderData($entryBuilder);

        foreach (ServiceFactory::getItemService()->iterator() as $item) {
            $presetResult = is_array($providerPresets[$item->getUuid()] ?? null) ? $providerPresets[$item->getUuid()] : null;
            $presetData = $presetResult['preset'] ?? null;
            $presetParts = $presetResult['parts'] ?? null;

            $entry = $entryBuilder->buildEntry(
                $item,
                $providerTargets[$item->getUuid()] ?? null,
                $presetData,
                $presetParts
            );

            if ($entry !== null) {
                $this->mergeResourceEntry($resources, $entry);
            }

            $io->progressAdvance();
        }

        foreach ($providerEntries as $entry) {
            $this->mergeResourceEntry($resources, $entry);
        }

        for ($i = 0; $i < $providerCount; $i++) {
            $io->progressAdvance();
        }

        $this->collectCaveResources($entryBuilder, $resources, $io);

        $io->progressFinish();

        $resources = array_values($resources);

        usort(
            $resources,
            static fn (array $left, array $right): int => [$left['name'] ?? '', $left['key'] ?? '', $left['uuid'] ?? '']
                <=> [$right['name'] ?? '', $right['key'] ?? '', $right['uuid'] ?? '']
        );

        return $resources;
    }

    /**
     * @return array{
     *     list<list<array<string, mixed>>>,
     *     array<string, string>,
     *     array<string, array<string, mixed>>,
     * }
     */
    private function collectProviderData(ResourceIndexBuilder $entryBuilder): array
    {
        $providerEntries = [];
        $providerTargets = [];
        $providerPresets = [];

        foreach (ServiceFactory::getFoundryLookupService()->getDocumentType('HarvestableProviderPreset', HarvestableProviderPreset::class) as $provider) {
            $results = iterator_to_array($this->iterateHarvestableEntries($provider, $entryBuilder), false);
            $entries = [];

            foreach ($results as $result) {
                $entry = $result['entry'];
                $entries[] = $entry;

                if (empty($entry['uuid']) || $entry['kind'] === null) {
                    continue;
                }

                $providerTargets[$entry['uuid']] ??= $entry['kind'];

                if ($entry['kind'] === 'harvestable' && $result['preset'] !== null) {
                    $providerPresets[$entry['uuid']] = $result['preset'];
                }
            }

            array_push($providerEntries, ...$entries);
        }

        return [$providerEntries, $providerTargets, $providerPresets];
    }

    /**
     * @param  list<array<string, mixed>>  $resources
     * @return list<array<string, mixed>>
     */
    private function buildLocationsExport(array $resources, SymfonyStyle $io): array
    {
        $lookup = ServiceFactory::getFoundryLookupService();
        $resolver = new HarvestableProviderStarmapResolver;
        $qualityResolver = new QualityRangeResolver;
        $resourceIndex = $this->indexResourcesByUuid($resources);
        $exports = [];

        $io->section('Building mining locations');
        $io->progressStart($lookup->countDocumentType('HarvestableProviderPreset'));

        foreach ($lookup->getDocumentType('HarvestableProviderPreset', HarvestableProviderPreset::class) as $provider) {
            $formatted = new ResourceLocationFormat($provider, $resolver, $qualityResolver, $resourceIndex);
            $export = $formatted->toArray();

            if ($export !== null) {
                $exports[] = $export;
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        $this->buildCaveLocationExports($resourceIndex, $exports, $resolver, $io);

        usort($exports, static fn (array $left, array $right): int => [
            $left['locations'][0]['system'] ?? null,
            $left['locations'][0]['name'] ?? null,
            $left['provider']['name'] ?? null,
            $left['provider']['uuid'] ?? null,
        ] <=> [
            $right['locations'][0]['system'] ?? null,
            $right['locations'][0]['name'] ?? null,
            $right['provider']['name'] ?? null,
            $right['provider']['uuid'] ?? null,
        ]);

        return $exports;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function indexResourcesByUuid(array $resources): array
    {
        $index = [];

        foreach ($resources as $resource) {
            if (empty($resource['uuid'])) {
                continue;
            }

            $index[$resource['uuid']] = $resource;
        }

        return $index;
    }

    /**
     * @return iterable<int, array{entry: array<string, mixed>, preset: array{preset: array<string, mixed>, parts: list<array<string, mixed>>|null}|null}>
     */
    private function iterateHarvestableEntries(
        HarvestableProviderPreset $provider,
        ResourceIndexBuilder $entryBuilder
    ): iterable {
        foreach ($provider->getHarvestableGroups() as $group) {
            foreach ($group->getHarvestableElements() as $element) {
                $result = $this->buildHarvestableEntry($element, $group->getName(), $entryBuilder);

                if ($result !== null) {
                    yield $result;
                }
            }
        }
    }

    /**
     * @return array{entry: array<string, mixed>, preset: array{preset: array<string, mixed>, parts: list<array<string, mixed>>|null}|null}|null
     */
    private function buildHarvestableEntry(
        HarvestableElement $element,
        ?string $groupName,
        ResourceIndexBuilder $entryBuilder
    ): ?array {
        $harvestablePreset = $element->getHarvestable();
        $entityClass = $this->resolveEntityClass($element, $harvestablePreset);

        if (! $entityClass instanceof EntityClassDefinition) {
            return null;
        }

        $kind = $this->resolveCanonicalKind($entityClass, $harvestablePreset, $groupName);

        $preset = $kind === 'harvestable'
            ? $this->resolvePresetFromHarvestable($harvestablePreset, $entryBuilder)
            : null;

        $entry = $entryBuilder->buildEntry(
            $entityClass,
            $kind,
            $preset['preset'] ?? null,
            $preset['parts'] ?? null
        );

        if ($entry === null) {
            return null;
        }

        return ['entry' => $entry, 'preset' => $preset];
    }

    /**
     * @return array{preset: array<string, mixed>, parts: list<array<string, mixed>>|null}|null
     */
    private function resolvePresetFromHarvestable(?HarvestablePreset $preset, ResourceIndexBuilder $entryBuilder): ?array
    {
        if ($preset === null) {
            return null;
        }

        $presetData = $this->removeNullValues([
            'harvestable_uuid' => $preset->getUuid(),
            'harvestable_key' => $preset->getClassName(),
            'respawn_in_slot_time' => $preset->getRespawnInSlotTime(),
            'despawn_time_seconds' => $preset->getDespawnTimeSeconds(),
            'additional_wait_for_nearby_players_seconds' => $preset->getAdditionalWaitForNearbyPlayersSeconds(),
        ]);

        $parts = $entryBuilder->extractSubHarvestableParts($preset);
        if ($parts === []) {
            $parts = null;
        }

        return ['preset' => $presetData, 'parts' => $parts];
    }

    /**
     * @param  array<string, array<string, mixed>>  $index
     * @param  array<string, mixed>  $candidate
     */
    private function mergeResourceEntry(array &$index, array $candidate): void
    {
        if (empty($candidate['uuid'])) {
            return;
        }

        $existing = $index[$candidate['uuid']] ?? null;

        if (! is_array($existing)) {
            $index[$candidate['uuid']] = $candidate;

            return;
        }

        foreach (self::MERGEABLE_FIELDS as $field) {
            if (($existing[$field] ?? null) === null && array_key_exists($field, $candidate)) {
                $existing[$field] = $candidate[$field];
            }
        }

        $existing['kind'] ??= $candidate['kind'] ?? null;

        $index[$candidate['uuid']] = $existing;
    }

    /**
     * @param  list<array<string, mixed>>  $resources
     */
    private function collectCaveResources(ResourceIndexBuilder $entryBuilder, array &$resources, SymfonyStyle $io): void
    {
        $lookup = ServiceFactory::getFoundryLookupService();

        foreach ($lookup->getDocumentType('SubHarvestableMultiConfigRecord', SubHarvestableMultiConfigRecord::class) as $config) {
            $this->processCaveConfigRecord($config, $entryBuilder, $resources);
            $io->progressAdvance();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $resources
     */
    private function processCaveConfigRecord(SubHarvestableMultiConfigRecord $config, ResourceIndexBuilder $entryBuilder, array &$resources): void
    {
        foreach ($config->getTaggedConfigs() as $taggedConfig) {
            foreach ($taggedConfig->getSubHarvestableSlots() as $slot) {
                $this->processCaveSlot($slot, $entryBuilder, $resources);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $resources
     */
    private function processCaveSlot(\Octfx\ScDataDumper\DocumentTypes\Harvestable\SubHarvestableSlot $slot, ResourceIndexBuilder $entryBuilder, array &$resources): void
    {
        $entityClass = $this->resolveCaveSlotEntityClass($slot);
        if ($entityClass === null) {
            return;
        }

        $existing = $resources[$entityClass->getUuid()] ?? null;
        if (is_array($existing)) {
            return;
        }

        $harvestablePreset = $slot->getHarvestable();
        $preset = $this->resolvePresetFromHarvestable($harvestablePreset, $entryBuilder);

        $entry = $entryBuilder->buildEntry(
            $entityClass,
            'cave_harvestable',
            $preset['preset'] ?? null,
            $preset['parts'] ?? null,
        );

        if ($entry !== null) {
            $this->mergeResourceEntry($resources, $entry);
        }
    }

    private function resolveCaveSlotEntityClass(\Octfx\ScDataDumper\DocumentTypes\Harvestable\SubHarvestableSlot $slot): ?EntityClassDefinition
    {
        $entityClassRef = $slot->getHarvestableEntityClassReference();
        if ($entityClassRef !== null) {
            $resolved = ServiceFactory::getItemService()->getByReference($entityClassRef);
            if ($resolved instanceof EntityClassDefinition) {
                return $resolved;
            }
        }

        $harvestablePreset = $slot->getHarvestable();
        $entityClassRef = $harvestablePreset?->getEntityClassReference();
        if ($entityClassRef !== null) {
            $resolved = ServiceFactory::getItemService()->getByReference($entityClassRef);
            if ($resolved instanceof EntityClassDefinition) {
                return $resolved;
            }
        }

        return $harvestablePreset?->getEntityClass();
    }

    /**
     * @param  array<string, array<string, mixed>>  $resourceIndex
     * @param  list<array<string, mixed>>  $exports
     */
    private function buildCaveLocationExports(array $resourceIndex, array &$exports, HarvestableProviderStarmapResolver $resolver, SymfonyStyle $io): void
    {
        $lookup = ServiceFactory::getFoundryLookupService();
        $caveResolver = new CaveHarvestableResolver;

        $io->section('Building cave locations');
        $caveConfigCount = $lookup->countDocumentType('SubHarvestableMultiConfigRecord');
        $io->progressStart($caveConfigCount);

        foreach ($lookup->getDocumentType('SubHarvestableMultiConfigRecord', SubHarvestableMultiConfigRecord::class) as $config) {
            $export = $this->buildCaveLocationEntry($config, $caveResolver, $resolver, $resourceIndex);

            if ($export !== null) {
                $exports[] = $export;
            }

            $io->progressAdvance();
        }

        $io->progressFinish();
    }

    /**
     * @param  array<string, array<string, mixed>>  $resourceIndex
     * @return array<string, mixed>|null
     */
    private function buildCaveLocationEntry(
        SubHarvestableMultiConfigRecord $config,
        CaveHarvestableResolver $caveResolver,
        HarvestableProviderStarmapResolver $starmapResolver,
        array $resourceIndex
    ): ?array {
        $resolved = $caveResolver->resolveCaveLocations($config);

        if ($resolved['locations'] === []) {
            return null;
        }

        $starmapLocations = array_map(function (array $loc) use ($starmapResolver): array {
            $match = $starmapResolver->resolveByClassName($loc['className']);

            return [
                'key' => $loc['className'],
                'object' => $match['starmapObjectUuid'] ?? null,
                'location' => $match['starmapLocationHierarchyTagName'] ?? null,
                'tag' => $match['starmapLocationHierarchyTagUuid'] ?? null,
                'matchStrategy' => $match !== null ? 'cave_starmap' : 'cave',
                'system' => $loc['system'],
                'name' => $match['name'] ?? $loc['className'],
                'type' => 'cave',
            ];
        }, $resolved['locations']);

        $groups = [];
        foreach ($config->getTaggedConfigs() as $taggedConfig) {
            $groups[] = $this->buildCaveGroupEntry($taggedConfig, $resourceIndex);
        }

        return $this->transformArrayKeysToPascalCase($this->removeNullValues([
            'provider' => [
                'uuid' => $config->getUuid(),
                'name' => $config->getClassName(),
                'preset_file' => pathinfo($config->getPath(), PATHINFO_FILENAME),
                'type' => 'cave',
            ],
            'locations' => $starmapLocations,
            'areas' => [],
            'groups' => $groups,
            'cave_config' => $this->removeNullValues([
                'cave_type' => $resolved['caveType'],
                'occupancy' => $resolved['occupancy'],
                'system' => $resolved['system'],
            ]),
        ]));
    }

    /**
     * @param  array<string, array<string, mixed>>  $resourceIndex
     * @return array<string, mixed>
     */
    private function buildCaveGroupEntry(TaggedSubHarvestableConfig $taggedConfig, array $resourceIndex): array
    {
        $deposits = [];
        $slots = $taggedConfig->getSubHarvestableSlots();
        $totalRelativeProbability = 0.0;
        $hasRelativeProbability = false;

        foreach ($slots as $slot) {
            $relativeProbability = $slot->getRelativeProbability();
            if ($relativeProbability === null) {
                continue;
            }
            $totalRelativeProbability += $relativeProbability;
            $hasRelativeProbability = true;
        }

        foreach ($slots as $slot) {
            $relativeProbability = $slot->getRelativeProbability();
            $entityClassUuid = $this->resolveCaveSlotEntityClassUuid($slot);
            $resource = $entityClassUuid !== null ? ($resourceIndex[$entityClassUuid] ?? null) : null;

            if ($entityClassUuid === null) {
                continue;
            }

            $normalizedProbability = $hasRelativeProbability && is_numeric($relativeProbability) && $totalRelativeProbability > 0.0
                ? $relativeProbability / $totalRelativeProbability
                : null;

            $deposits[] = $this->removeNullValues([
                'resource_uuid' => $entityClassUuid,
                'relative_probability' => $normalizedProbability,
                'resource_qualities' => null,
                'clustering' => null,
                'harvestable_setup' => null,
            ]);
        }

        return $this->removeNullValues([
            'group_name' => $taggedConfig->getName(),
            'group_probability' => $taggedConfig->getInitialSlotsProbability(),
            'deposits' => $deposits,
        ]);
    }

    private function resolveCaveSlotEntityClassUuid(\Octfx\ScDataDumper\DocumentTypes\Harvestable\SubHarvestableSlot $slot): ?string
    {
        $entityClassRef = $slot->getHarvestableEntityClassReference();
        if ($entityClassRef !== null) {
            return $entityClassRef;
        }

        $harvestablePreset = $slot->getHarvestable();
        if ($harvestablePreset !== null) {
            $presetEntityRef = $harvestablePreset->getEntityClassReference();
            if ($presetEntityRef !== null) {
                return $presetEntityRef;
            }
        }

        return null;
    }

    private function resolveCanonicalKind(
        ?EntityClassDefinition $entityClass,
        ?HarvestablePreset $harvestablePreset,
        ?string $groupName
    ): string {
        $groupNameLower = strtolower((string) $groupName);
        $presetKeyLower = strtolower((string) $harvestablePreset?->getClassName());

        if ($entityClass?->getMineableParams() !== null) {
            return 'mineable';
        }

        if (str_contains($groupNameLower, 'salvage') || str_contains($presetKeyLower, 'salvage')) {
            return 'salvageable';
        }

        return 'harvestable';
    }

    private function resolveEntityClass(
        HarvestableElement $element,
        ?HarvestablePreset $harvestablePreset
    ): ?EntityClassDefinition {
        $ref = $element->getHarvestableEntityClassReference()
            ?? $harvestablePreset?->getEntityClassReference();

        if ($ref !== null && $ref !== '') {
            $resolved = ServiceFactory::getItemService()->getByReference($ref);

            if ($resolved instanceof EntityClassDefinition) {
                return $resolved;
            }
        }

        return $element->getHarvestableEntity()
            ?? $harvestablePreset?->getEntityClass();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function removeNullValues(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = $this->removeNullValues($value);
            }

            if ($value === null) {
                unset($data[$key]);

                continue;
            }

            $data[$key] = $value;
        }

        return $data;
    }
}
