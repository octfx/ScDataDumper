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
use Octfx\ScDataDumper\Services\HarvestableProviderStarmapResolver;
use Octfx\ScDataDumper\Services\Mining\MiningQualityRangeResolver;
use Octfx\ScDataDumper\Services\ServiceFactory;
use RuntimeException;

final class MineableLocation extends BaseFormat
{
    /**
     * @param array<string, array<string, mixed>> $mineableIndex
     */
    public function __construct(
        HarvestableProviderPreset $provider,
        private readonly HarvestableProviderStarmapResolver $resolver,
        private readonly MiningQualityRangeResolver $qualityResolver,
        private readonly array $mineableIndex
    ) {
        parent::__construct($provider);
    }

    public function toArray(): ?array
    {
        if (! $this->item instanceof HarvestableProviderPreset) {
            return null;
        }

        $provider = $this->item;
        $location = $this->resolver->resolveHarvestableProvider($provider);
        $locationName = is_string($location['locationName'] ?? null) ? $location['locationName'] : null;
        $systemName = is_string($location['systemKey'] ?? null) ? $location['systemKey'] : null;

        return [
            'provider' => [
                'uuid' => $provider->getUuid(),
                'name' => $provider->getClassName(),
                'presetFile' => $location['presetFile'],
            ],
            'location' => [
                'system' => $location['systemKey'],
                'name' => $location['locationName'],
                'type' => $location['locationType'],
            ],
            'starmap' => [
                'key' => $location['starmapKey'],
                'object' => $location['starmapObjectUuid'],
                'location' => $location['starmapLocationHierarchyTagName'],
                'tag' => $location['starmapLocationHierarchyTagUuid'],
                'matchStrategy' => $location['matchStrategy'],
            ],
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
        $mineable = $entityClassUuid !== null ? ($this->mineableIndex[strtolower($entityClassUuid)] ?? null) : null;
        $harvestablePreset = $this->resolveHarvestablePreset($element);
        $entityClass = $this->resolveEntityClass($element, $entityClassUuid);
        $setup = $this->resolveHarvestableSetup($element);
        $clustering = $this->resolveHarvestableClustering($element);
        $composition = $this->buildCompositionSummary(
            $mineable['composition'] ?? null,
            $entityClass?->getMineableParams()?->getCompositionReference(),
            $locationName,
            $systemName
        );

        if ($relativeProbability === null && $entityClassUuid === null && $harvestablePreset === null && $setup === null && $clustering === null) {
            return null;
        }

        return $this->removeNullValues([
            'relativeProbability' => $relativeProbability,
            'uuid' => $entityClassUuid,
            'name' => $mineable['name'] ?? $this->resolveDepositName($harvestablePreset, $entityClass),
            'signature' => is_numeric($mineable['signature'] ?? null) ? (float) $mineable['signature'] : null,
            'composition' => $composition,
            'clustering' => $this->buildClusteringSummary($clustering, $element->getClusteringReference()),
            'harvestableSetup' => $this->buildHarvestableSetupSummary($setup, $element->getHarvestableSetupReference()),
            'harvestablePreset' => $this->buildHarvestablePresetSummary($harvestablePreset, $element->getHarvestableReference()),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildCompositionSummary(
        mixed $composition,
        ?string $reference,
        ?string $locationName,
        ?string $systemName
    ): ?array {
        $uuid = $this->normalizeValue($reference);

        if (! is_array($composition)) {
            return $uuid !== null ? ['uuid' => $uuid] : null;
        }

        $parts = [];

        foreach (($composition['parts'] ?? []) as $part) {
            if (! is_array($part)) {
                continue;
            }

            $resourceType = $part['resource_type'] ?? null;
            $resourceTypeUuid = is_array($resourceType) && is_string($resourceType['uuid'] ?? null)
                ? $resourceType['uuid']
                : null;
            $qualityScale = is_numeric($part['quality_scale'] ?? null) ? (float) $part['quality_scale'] : null;

            $qualityRange = $this->qualityResolver->resolveForResourceTypeReference(
                $resourceTypeUuid,
                $qualityScale,
                $locationName,
                $systemName
            );

            if ($qualityRange !== null) {
                $part['quality_range'] = [
                    'min' => $qualityRange['effective_min'],
                    'max' => $qualityRange['effective_max'],
                ];
            }

            if (array_key_exists('resource_type', $part)) {
                $part['resource'] = $part['resource_type'];
                unset($part['resource_type']);
            }

            unset($part['quality_scale'], $part['curve_exponent']);
            $parts[] = $part;
        }

        $composition['parts'] = $parts;
        $composition['uuid'] = $uuid;

        return $composition;
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

    private function resolveDepositName(?HarvestablePreset $harvestablePreset, ?EntityClassDefinition $entityClass): ?string
    {
        /** @var mixed $displayName */
        $displayName = $harvestablePreset?->get('@displayName');

        return $this->translate(is_string($displayName) ? $displayName : null)
            ?? $harvestablePreset?->getClassName()
            ?? $entityClass?->getClassName();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildHarvestableSetupSummary(?HarvestableSetup $setup, ?string $reference): ?array
    {
        $uuid = $this->normalizeValue($reference ?? $setup?->getUuid());

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
        $uuid = $this->normalizeValue($reference ?? $clustering?->getUuid());
        $params = $clustering?->getParams() ?? [];

        if ($clustering === null && $uuid === null) {
            return null;
        }

        return [
            'uuid' => $uuid,
            'key' => $clustering?->getClassName(),
            'probability_of_clustering' => $this->normalizePercentage($clustering?->getProbabilityOfClustering()),
            'summary' => $this->buildClusteringSummaryMetadata($params),
            'params' => $this->normalizeClusteringParams($params),
        ];
    }

    /**
     * @param list<array<string, mixed>> $params
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
     * @param list<array<string, mixed>> $params
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
     * @param list<array<string, mixed>> $params
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

    /**
     * @return array<string, mixed>|null
     */
    private function buildHarvestablePresetSummary(?HarvestablePreset $preset, ?string $reference): ?array
    {
        $uuid = $this->normalizeValue($reference ?? $preset?->getUuid());

        if ($preset === null && $uuid === null) {
            return null;
        }

        return [
            'uuid' => $uuid,
            'key' => $preset?->getClassName(),
        ];
    }

    private function normalizeValue(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalizedValue = trim($value);

        return $normalizedValue === '' ? null : $normalizedValue;
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
