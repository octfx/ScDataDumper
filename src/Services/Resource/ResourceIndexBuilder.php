<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Resource;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\DocumentTypes\Harvestable\HarvestablePreset;
use Octfx\ScDataDumper\DocumentTypes\Mining\MineableCompositionPart;
use Octfx\ScDataDumper\DocumentTypes\Mining\MiningGlobalParams;
use Octfx\ScDataDumper\DocumentTypes\ResourceType;
use Octfx\ScDataDumper\Formats\ScUnpacked\ResourceContainer;
use Octfx\ScDataDumper\Services\FoundryLookupService;
use Octfx\ScDataDumper\Services\ItemService;
use Octfx\ScDataDumper\Services\LocalizationService;
use RuntimeException;

final readonly class ResourceIndexBuilder
{
    public function __construct(
        private LocalizationService $localizationService,
        private FoundryLookupService $foundryLookupService,
        private ItemService $itemService,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function buildEntry(
        EntityClassDefinition $item,
        ?string $providerKind = null,
        ?array $providerPreset = null,
        ?array $presetParts = null,
    ): ?array {
        $resource = $this->buildResourceEntry($item);

        if ($resource !== null) {
            return $resource;
        }

        if ($providerKind === null) {
            return null;
        }

        $entry = $this->extractIdentity($item);
        $entry['kind'] = $providerKind;
        $entry['signature'] = $item->getRsSignature();
        if ($providerKind === 'harvestable' && is_array($providerPreset)) {
            $entry = array_merge($providerPreset, $entry);
            $entry['parts'] = $this->buildHarvestableParts($item, $presetParts);
        }

        return $this->removeNullValues($entry);
    }

    /**
     * @param  list<array<string, mixed>>|null  $presetParts
     * @return list<array<string, mixed>>
     */
    public function extractSubHarvestableParts(HarvestablePreset $preset): array
    {
        $parts = [];

        foreach ($preset->getSubHarvestableSlots() as $slot) {
            $subPreset = $slot->getHarvestable();
            $entityClass = $subPreset?->getEntityClass();

            if (! $entityClass instanceof EntityClassDefinition) {
                continue;
            }

            $part = $this->toPart($entityClass);

            if ($part !== null) {
                $part['relative_probability'] = $slot->getRelativeProbability();
                $part['respawn_time_multiplier'] = $slot->getHarvestableRespawnTimeMultiplier();
                $part['min_count'] = $slot->getMinCount();
                $part['max_count'] = $slot->getMaxCount();
                $parts[] = $this->removeNullValues($part);
            }
        }

        return $parts;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractIdentity(EntityClassDefinition|ResourceType $entity): array
    {
        $name = $entity instanceof EntityClassDefinition
            ? $this->localizationService->translateValue($entity->getAttachDef()?->get('Localization@Name'))
            : $this->localizationService->translateValue($entity->getDisplayName());

        return [
            'uuid' => $entity->getUuid(),
            'key' => $entity->getClassName(),
            'name' => !empty($name) ? $name : $entity->getClassName(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>|null  $presetParts
     * @return list<array<string, mixed>>
     */
    private function buildHarvestableParts(EntityClassDefinition $item, ?array $presetParts): array
    {
        if ($presetParts !== null && $presetParts !== []) {
            return $presetParts;
        }

        $part = $this->toPart($item);

        return $part !== null ? [$part] : [];
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
    private function buildResourceEntry(EntityClassDefinition $item): ?array
    {
        $mineableParams = $item->getMineableParams();
        $composition = $mineableParams?->getComposition();
        $depositName = $this->localizationService->translateValue($composition?->getDepositName());

        if ($mineableParams === null || $composition === null) {
            return null;
        }

        $entry = $this->extractIdentity($item);
        $entry['kind'] = 'mineable';
        $entry['signature'] = $item->getRsSignature();
        $entry['global_params'] = $this->toGlobalParams($mineableParams->getGlobalParams());
        $entry['composition'] = [
            'uuid' => $composition->getUuid(),
            'deposit_name' => $depositName !== '' ? $depositName : null,
            'minimum_distinct_elements' => $composition->getMinimumDistinctElements(),
            'parts' => array_values(array_map(
                fn (MineableCompositionPart $part): array => $this->toCompositionPart($part),
                $composition->getParts()
            )),
        ];

        $resolver = new QualityTierResolver;

        $tier = null;
        $firstPart = $composition->getParts()[0] ?? null;
        if ($firstPart !== null) {
            $qualityDist = $firstPart->getMineableElement()?->getResourceType()?->getQualityDistribution();
            if ($qualityDist !== null) {
                $category = $resolver->extractCategoryFromPath($qualityDist->getPath());
                $extracted = $resolver->extractTierFromClassName($qualityDist->getClassName(), $category);
                $tier = $extracted !== 'default' ? $extracted : null;
            }
        }
        $entry['tier'] = $tier;

        return $this->removeNullValues($entry);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function toPart(EntityClassDefinition $entity): ?array
    {
        $resourceContainer = new ResourceContainer($entity)->toArray();

        if (! is_array($resourceContainer)) {
            return null;
        }

        $resourceTypes = [];

        foreach (($resourceContainer['DefaultComposition'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $reference = is_string($entry['Entry'] ?? null) ? $entry['Entry'] : null;
            $resolved = $this->resolveReferenceToIdentity($reference);

            if ($resolved !== null) {
                $resolved['weight'] = $entry['Weight'] ?? null;
                if (isset($resolved['uuid'])) {
                    $resolved['resource_type_uuid'] = $resolved['uuid'];
                    unset($resolved['uuid']);
                }
                $resourceTypes[] = $this->removeNullValues($resolved);
            }
        }

        return $this->removeNullValues([
            ...$this->extractIdentity($entity),
            'resource_types' => $resourceTypes === [] ? null : $resourceTypes,
            'immutable' => array_key_exists('Immutable', $resourceContainer) ? (bool) $resourceContainer['Immutable'] : null,
            'fill_fraction' => $resourceContainer['DefaultFillFraction'] ?? null,
            'capacity' => $this->normalizeCapacity($resourceContainer['Capacity'] ?? null),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveReferenceToIdentity(?string $reference): ?array
    {
        if ($reference === null || trim($reference) === '') {
            return null;
        }

        $reference = trim($reference);

        try {
            $resourceType = $this->foundryLookupService->getResourceTypeByReference($reference);
        } catch (RuntimeException) {
            $resourceType = null;
        }

        if ($resourceType instanceof ResourceType) {
            return $this->extractIdentity($resourceType);
        }

        $entity = $this->itemService->getByReference($reference);

        if ($entity instanceof EntityClassDefinition) {
            return $this->extractIdentity($entity);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeCapacity(mixed $capacity): ?array
    {
        if (! is_array($capacity)) {
            return null;
        }

        return $this->removeNullValues([
            'unit_name' => $capacity['UnitName'] ?? null,
            'value' => $capacity['Value'] ?? null,
        ]);
    }

    private function normalizePercentage(float|int|null $value): float|int|null
    {
        if ($value === null) {
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
    private function toGlobalParams(?MiningGlobalParams $globalParams): array
    {
        $wasteResourceType = $this->resolveResourceType(
            $globalParams?->getWasteResourceTypeReference(),
            $globalParams?->getWasteResourceType()
        );

        $wasteIdentity = $this->toResourceTypeIdentity($wasteResourceType, false);

        return $this->removeNullValues([
            'power_capacity_per_mass' => $globalParams?->getPowerCapacityPerMass(),
            'decay_per_mass' => $globalParams?->getDecayPerMass(),
            'optimal_window_size' => $globalParams?->getOptimalWindowSize(),
            'optimal_window_factor' => $globalParams?->getOptimalWindowFactor(),
            'optimal_window_max_size' => $globalParams?->getOptimalWindowMaxSize(),
            'resistance_curve_factor' => $globalParams?->getResistanceCurveFactor(),
            'optimal_window_thinness_curve_factor' => $globalParams?->getOptimalWindowThinnessCurveFactor(),
            'c_scu_per_volume' => $globalParams?->getCScuPerVolume(),
            'default_mass' => $globalParams?->getDefaultMass(),
            'waste_resource_type_uuid' => $wasteIdentity['uuid'] ?? null,
            'waste_resource_type_key' => $wasteIdentity['key'] ?? null,
            'waste_resource_type_name' => $wasteIdentity['name'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toCompositionPart(MineableCompositionPart $part): array
    {
        $mineableElement = $part->getMineableElement();
        $resourceType = $this->resolveResourceType(
            $mineableElement?->getResourceTypeReference(),
            $mineableElement?->getResourceType()
        );

        $resourceTypeIdentity = $this->toResourceTypeIdentity($resourceType);

        unset($resourceTypeIdentity['uuid']);

        $resourceTypeIdentity = [
            'resource_type_uuid' => $resourceType?->getUuid(),
            ...$resourceTypeIdentity,
        ];

        return [
            'uuid' => $part->getMineableElementReference(),
            ...$resourceTypeIdentity,
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

        $resolvedResourceType = $this->foundryLookupService->getResourceTypeByReference($reference);

        return $resolvedResourceType ?? $resourceType;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function toResourceTypeIdentity(?ResourceType $resourceType, bool $includeDensity = true): ?array
    {
        if ($resourceType === null) {
            return null;
        }

        $summary = $this->extractIdentity($resourceType);

        if ($includeDensity) {
            $summary['density_g_per_cc'] = $resourceType->getDensityGramsPerCubicCentimeter();
        }

        return $summary;
    }

    private function removeNullValues(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = $this->removeNullValues($value);

                if ($value === []) {
                    unset($data[$key]);

                    continue;
                }
            }

            if ($value === null) {
                unset($data[$key]);
            }
        }

        unset($value);

        return $data;
    }
}
