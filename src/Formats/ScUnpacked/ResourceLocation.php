<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestableClusterPreset;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestableElement;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestableElementGroup;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestablePreset;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestableProviderPreset;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestableSetup;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\SubHarvestableSlot;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Services\Resource\HarvestableProviderStarmapResolver;
use Octfx\ScDataDumper\Services\Resource\QualityRangeResolver;
use Octfx\ScDataDumper\Services\ServiceFactory;

final class ResourceLocation extends BaseFormat
{
    /**
     * @param  array<string, array<string, mixed>>  $resourceIndex
     */
    public function __construct(
        HarvestableProviderPreset $provider,
        private readonly HarvestableProviderStarmapResolver $resolver,
        private readonly QualityRangeResolver $qualityResolver,
        private readonly array $resourceIndex
    ) {
        parent::__construct($provider);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function toArray(): ?array
    {
        if (! $this->item instanceof HarvestableProviderPreset) {
            return null;
        }

        $provider = $this->item;
        $resolved = $this->resolver->resolveHarvestableProvider($provider);

        return $this->transformArrayKeysToPascalCase($this->buildLocationEntry($provider, $resolved));
    }

    /**
     * @param  array{locationName: string, systemKey: ?string, presetFile: string, starmapKey: string, locationType: string, starmapObjectUuid: ?string, starmapLocationHierarchyTagUuid: ?string, starmapLocationHierarchyTagName: ?string, matchStrategy: string, locations: list<array{className: string, starmapObjectUuid: ?string, starmapLocationHierarchyTagUuid: ?string, starmapLocationHierarchyTagName: ?string, name: string, type: string}>}  $resolved
     * @return array<string, mixed>
     */
    private function buildLocationEntry(HarvestableProviderPreset $provider, array $resolved): array
    {
        $locationName = is_string($resolved['locationName'] ?? null) ? $resolved['locationName'] : null;
        $systemName = is_string($resolved['systemKey'] ?? null) ? $resolved['systemKey'] : null;

        $starmapLocations = array_map(static fn (array $loc): array => [
            'key' => $loc['className'],
            'object' => $loc['starmapObjectUuid'],
            'location' => $loc['starmapLocationHierarchyTagName'],
            'tag' => $loc['starmapLocationHierarchyTagUuid'],
            'matchStrategy' => $resolved['matchStrategy'],
            'system' => $systemName,
            'name' => $loc['name'],
            'type' => $loc['type'],
        ], $resolved['locations'] ?? []);

        if ($starmapLocations === []) {
            $starmapLocations = [[
                'key' => null,
                'object' => null,
                'location' => null,
                'tag' => null,
                'matchStrategy' => $resolved['matchStrategy'],
                'system' => $systemName,
                'name' => $locationName,
                'type' => $resolved['locationType'],
            ]];
        }

        return [
            'provider' => [
                'uuid' => $provider->getUuid(),
                'name' => $provider->getClassName(),
                'presetFile' => $resolved['presetFile'],
            ],
            'locations' => $starmapLocations,
            'areas' => array_values(array_map(
                static fn (array $area): array => [
                    'name' => $area['name'] ?? null,
                    'globalModifier' => $area['globalModifier'] ?? null,
                ],
                $provider->getAreas()
            )),
            'groups' => array_values(array_map(
                fn (HarvestableElementGroup $group): array => $this->buildGroupExportEntry($group, $locationName, $systemName),
                $provider->getHarvestableGroups()
            )),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGroupExportEntry(
        HarvestableElementGroup $group,
        ?string $locationName,
        ?string $systemName
    ): array {
        $deposits = [];
        $elements = $group->getHarvestableElements();
        $totalRelativeProbability = 0.0;
        $hasRelativeProbability = false;

        foreach ($elements as $element) {
            $relativeProbability = $element->getRelativeProbability();

            if ($relativeProbability === null) {
                continue;
            }

            $totalRelativeProbability += $relativeProbability;
            $hasRelativeProbability = true;
        }

        foreach ($elements as $element) {
            $relativeProbability = $element->getRelativeProbability();
            $deposit = $this->buildDepositExportEntry(
                $element,
                $hasRelativeProbability && is_numeric($relativeProbability) && $totalRelativeProbability > 0.0
                    ? $relativeProbability / $totalRelativeProbability
                    : null,
                $locationName,
                $systemName
            );

            if ($deposit !== null) {
                $deposits[] = $deposit;
            }
        }

        return [
            'groupName' => $group->getName(),
            'groupProbability' => $this->normalizePercentage($group->getProbability()),
            'deposits' => $deposits,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildDepositExportEntry(
        HarvestableElement $element,
        ?float $relativeProbability,
        ?string $locationName,
        ?string $systemName
    ): ?array {
        $entityClassUuid = $this->resolveEntityClassUuid($element);
        $resource = $entityClassUuid !== null ? ($this->resourceIndex[$entityClassUuid] ?? null) : null;
        $clustering = $this->resolveHarvestableClustering($element);
        $setup = $this->resolveHarvestableSetup($element);
        $entityClass = $this->resolveEntityClass($element, $entityClassUuid);

        if ($entityClassUuid === null) {
            return null;
        }

        $qualityOverrides = [];

        if (is_array($resource) && ($resource['kind'] ?? null) === 'mineable') {
            $qualityOverrides = $this->buildQualityOverrides(
                $resource['composition'] ?? null,
                $locationName,
                $systemName
            );
        }

        $kind = is_string($resource['kind'] ?? null) ? $resource['kind'] : null;

        return $this->removeNullValues([
            'resource_uuid' => $entityClassUuid,
            'relativeProbability' => $relativeProbability,
            'resource_qualities' => $qualityOverrides === [] ? null : $qualityOverrides,
            'clustering' => $this->buildClusteringSummary($clustering, $element->getClusteringReference()),
            'harvestableSetup' => $kind !== 'mineable'
                ? $this->buildHarvestableSetupSummary($setup, $element->getHarvestableSetupReference())
                : null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildQualityOverrides(
        mixed $composition,
        ?string $locationName,
        ?string $systemName
    ): array {
        if (! is_array($composition)) {
            return [];
        }

        $overrides = [];

        foreach (($composition['parts'] ?? []) as $part) {
            if (! is_array($part)) {
                continue;
            }

            $resourceTypeUuid = is_string($part['resource_type_uuid'] ?? null)
                ? $part['resource_type_uuid']
                : null;
            $resourceKey = is_string($part['key'] ?? null)
                ? $part['key']
                : null;
            $qualityScale = is_numeric($part['quality_scale'] ?? null) ? (float) $part['quality_scale'] : null;

            if ($resourceKey === null) {
                continue;
            }

            $qualityRange = $this->qualityResolver->resolveForResourceTypeReference(
                $resourceTypeUuid,
                $qualityScale,
                $locationName,
                $systemName
            );

            if ($qualityRange === null) {
                continue;
            }

            $overrides[] = [
                'resource_key' => $resourceKey,
                'quality_range' => [
                    'min' => $qualityRange['effective_min'],
                    'max' => $qualityRange['effective_max'],
                    'mean' => $qualityRange['effective_mean'],
                    'stddev' => $qualityRange['effective_stddev'],
                ],
            ];
        }

        return $overrides;
    }

    private function resolveEntityClassUuid(HarvestableElement $element): ?string
    {
        $harvestablePreset = $this->resolveHarvestablePreset($element);

        return $element->getHarvestableEntityClassReference()
            ?? $harvestablePreset?->getEntityClassReference()
            ?? $element->getHarvestableEntity()?->getUuid();
    }

    private function resolveEntityClass(HarvestableElement $element, ?string $entityClassUuid): ?EntityClassDefinition
    {
        if ($entityClassUuid !== null) {
            $resolved = ServiceFactory::getItemService()->getByReference($entityClassUuid);

            if ($resolved instanceof EntityClassDefinition) {
                return $resolved;
            }
        }

        return $element->getHarvestableEntity()
            ?? $this->resolveHarvestablePreset($element)?->getEntityClass();
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

    private function resolveHarvestableSetup(HarvestableElement $element): ?HarvestableSetup
    {
        $reference = $element->getHarvestableSetupReference();

        if ($reference !== null) {
            $resolved = ServiceFactory::getFoundryLookupService()->getHarvestableSetupByReference($reference);

            if ($resolved instanceof HarvestableSetup) {
                return $resolved;
            }
        }

        return $element->getHarvestableSetup();
    }

    private function resolveHarvestableClustering(HarvestableElement $element): ?HarvestableClusterPreset
    {
        $reference = $element->getClusteringReference();

        if ($reference !== null) {
            $resolved = ServiceFactory::getFoundryLookupService()->getHarvestableClusterPresetByReference($reference);

            if ($resolved instanceof HarvestableClusterPreset) {
                return $resolved;
            }
        }

        return $element->getClustering();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildHarvestableSetupSummary(?HarvestableSetup $setup, ?string $reference): ?array
    {
        $uuid = $this->normalizeString($reference ?? $setup?->getUuid());

        if ($setup === null && $uuid === null) {
            return null;
        }

        return [
            'uuid' => $uuid,
            'key' => $setup?->getClassName(),
            'respawn_in_slot_time' => $setup?->getRespawnInSlotTime(),
            'despawn_time_seconds' => $setup?->getDespawnTimeSeconds(),
            'additional_wait_for_nearby_players_seconds' => $setup?->getAdditionalWaitForNearbyPlayersSeconds(),
            'movement_harvest_distance' => $setup?->getMovementHarvestDistance(),
            'required_health_ratio' => $setup?->getRequiredHealthRatio(),
            'required_damage_ratio' => $setup?->getRequiredDamageRatio(),
            'includes_attached_children_for_interaction' => $setup?->includesAttachedChildrenForInteraction(),
            'all_interactions_clear_spawn_point' => $setup?->doAllInteractionsClearSpawnPoint(),
            'special_harvestable_string' => $setup?->getSpecialHarvestableString(),
            'sub_harvestable_slots' => array_values(array_map(
                fn (SubHarvestableSlot $slot): array => $this->buildSubHarvestableSlotSummary($slot),
                $setup?->getSubHarvestableSlots() ?? []
            )),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSubHarvestableSlotSummary(SubHarvestableSlot $slot): array
    {
        return $this->removeNullValues([
            'harvestable' => $slot->getHarvestableReference(),
            'minCount' => $slot->getMinCount(),
            'maxCount' => $slot->getMaxCount(),
            'Harvestable' => $slot->getHarvestable()?->toArray(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildClusteringSummary(?HarvestableClusterPreset $clustering, ?string $reference): ?array
    {
        $uuid = $this->normalizeString($reference ?? $clustering?->getUuid());
        $params = $clustering?->getParams() ?? [];

        if ($clustering === null && $uuid === null) {
            return null;
        }

        return [
            'uuid' => $uuid,
            'key' => $clustering?->getClassName(),
            'probability_of_clustering' => $this->normalizePercentage($clustering?->getProbabilityOfClustering()),
            ...$this->buildClusteringSummaryMetadata($params),
            'params' => $this->normalizeClusteringParams($params),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $params
     * @return array<string, float|int>|null
     */
    private function buildClusteringSummaryMetadata(array $params): ?array
    {
        $summary = [
            'min_size' => $this->aggregateClusterParam($params, 'minSize', 'min'),
            'max_size' => $this->aggregateClusterParam($params, 'maxSize', 'max'),
            'min_proximity' => $this->aggregateClusterParam($params, 'minProximity', 'min'),
            'max_proximity' => $this->aggregateClusterParam($params, 'maxProximity', 'max'),
        ];

        return $this->removeNullValues($summary);
    }

    /**
     * @param  list<array<string, mixed>>  $params
     */
    private function aggregateClusterParam(array $params, string $field, string $mode): float|int|null
    {
        $values = [];

        foreach ($params as $param) {
            $value = $param[$field] ?? null;

            if (! is_numeric($value)) {
                continue;
            }

            $numericValue = (float) $value;
            $values[] = floor($numericValue) === $numericValue
                ? (int) $numericValue
                : $numericValue;
        }

        if ($values === []) {
            return null;
        }

        return $mode === 'min' ? min($values) : max($values);
    }

    /**
     * @param  list<array<string, mixed>>  $params
     * @return list<array<string, mixed>>
     */
    private function normalizeClusteringParams(array $params): array
    {
        $totalRelativeProbability = 0.0;

        foreach ($params as $param) {
            $relativeProbability = $param['relativeProbability'] ?? null;

            if (is_numeric($relativeProbability)) {
                $totalRelativeProbability += (float) $relativeProbability;
            }
        }

        $normalized = [];

        foreach ($params as $param) {
            if (is_numeric($param['relativeProbability'] ?? null) && $totalRelativeProbability > 0.0) {
                $param['relativeProbability'] = (float) $param['relativeProbability'] / $totalRelativeProbability;
            }

            $normalized[] = $param;
        }

        return $normalized;
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
}
