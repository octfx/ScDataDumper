<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use Illuminate\Support\Arr;
use Octfx\ScDataDumper\Helper\VehicleWrapper;

/**
 * Aggregates emission, power, and cooling data for vehicles
 *
 * Calculates IR and EM emissions with separate scenarios for shields up vs quantum drive active,
 * along with power segment usage and cooling capacity/usage metrics.
 */
final readonly class EmissionAggregator implements VehicleDataCalculator
{
    public function __construct(
        private StandardisedPartWalker $walker,
        private VehicleWrapper $wrapper,
    ) {}

    public function canCalculate(VehicleDataContext $context): bool
    {
        return true;
    }

    public function calculate(VehicleDataContext $context): array
    {
        $loadout = $context->standardisedParts;

        if (empty($loadout)) {
            return [];
        }

        $pools = $this->wrapper->entity->get('Components/SItemPortContainerComponentParams/resourceNetworkPowerPools/itemPools');

        $powerPools = [];
        $maxShields = 0;
        $weaponPoolMax = null;

        foreach ($pools?->children() ?? [] as $pool) {
            $itemType = $pool->get('@itemType');

            if ($itemType === 'Shield') {
                $maxShields = $pool->get('@maxItemCount');
            }

            if ($itemType === 'WeaponGun') {
                $weaponPoolMax = $pool->get('@poolSize');
            }

            $powerPools[$itemType] = [
                'type' => $pool->get('@__polymorphicType'),
                'item_type' => $itemType,
                'size' => $pool->get('@poolSize') ?? $pool->get('@maxItemCount'),
            ];
        }

        // Emission accumulators
        $irTotal = 0.0;

        $itemCounts = [];
        $emGroups = [];

        // Power and cooling segment usage
        $powerSegmentsUsage = [];
        $coolerSegmentsUsage = [];

        $powerGenerationSegments = 0.0;
        $powerGeneratorEM = 0.0;
        $coolantGenerationSegments = 0.0;

        /** @var array<int, array{segments: float, size: int}> $powerPlants */
        $powerPlants = [];

        $weaponPowerSegmentsUncapped = 0.0;

        $armorIrMultiplier = 1.0;
        $armorEmMultiplier = 1.0;

        foreach ($this->walker->walkItems($loadout) as $entry) {

            $item = $entry['Item'];

            if ($item === null) {
                continue;
            }

            $itemType = $item['type'];

            if (! isset($itemCounts[$itemType])) {
                $itemCounts[$itemType] = 0.0;
            }
            if (! isset($emGroups[$itemType])) {

                $emGroups[$itemType] = 0.0;
            }
            if (! isset($powerSegmentsUsage[$itemType])) {
                $powerSegmentsUsage[$itemType] = 0.0;
            }
            if (! isset($coolerSegmentsUsage[$itemType])) {
                $coolerSegmentsUsage[$itemType] = 0.0;
            }

            $stdItem = Arr::get($item, 'stdItem');
            $attachType = Arr::get($stdItem ?? [], 'Type') ?? Arr::get($item, 'type');
            $lowerType = strtolower((string) $attachType);

            $isShield = $itemType === 'Shield';
            $isQuantum = $itemType === 'QuantumDrive';
            $isGenerator = $itemType === 'PowerPlant';
            $isCooler = $itemType === 'Cooler';
            $isWeapon = in_array($itemType, ['Turret', 'TurretBase', 'WeaponGun']);
            $isFlightController = $itemType === 'FlightController';

            $resourceNetwork = Arr::get($stdItem, 'ResourceNetwork') ?: null;

            if ($isGenerator) {
                $powerGeneratorEM += Arr::get($stdItem, 'Emission.Em.Maximum', 0.0);
            }

            if (is_array($resourceNetwork)) {
                // EM Aggregation
                if ($isQuantum) {
                    $emGroups[$itemType] += Arr::get($stdItem, 'Emission.Em.Maximum', 0.0);
                    $itemCounts[$itemType]++;
                } elseif ($isShield) {
                    if ($itemCounts[$itemType] < $maxShields) {
                        $emGroups[$itemType] += Arr::get($stdItem, 'Emission.Em.Maximum', 0.0);
                    }
                } elseif ($isCooler) {
                    $emGroups[$itemType] += Arr::get($stdItem, 'Emission.Em.Maximum', 0.0);
                    $itemCounts[$itemType]++;
                } elseif ($isWeapon) {
                    $emGroups[$itemType] += Arr::get($stdItem, 'Emission.Em.Maximum', 0.0);
                } elseif ($isGenerator) {
                    $emGroups[$itemType] += Arr::get($stdItem, 'Emission.Em.Maximum', 0.0);
                    $itemCounts[$itemType]++;

                    // per-plant segments and size for multi-plant calculation
                    $genSegments = (float) Arr::get($resourceNetwork, 'Generation.Power', 0.0);
                    $plantSize = Arr::get($entry, 'Item.size');
                    if ($genSegments > 0) {
                        $powerPlants[] = ['segments' => $genSegments, 'size' => $plantSize];
                    }
                } else {
                    $emGroups[$itemType] += Arr::get($stdItem, 'Emission.Em.Maximum', 0.0);
                }

                // Cooler and Power Usage
                if ($isQuantum) {
                    $coolerSegmentsUsage[$itemType] += Arr::get($resourceNetwork, 'Usage.Coolant.Maximum', 0.0);
                    $coolerSegmentsUsage[$itemType] += Arr::get($resourceNetwork, 'Usage.Power.Maximum', 0.0);
                } elseif ($isShield) {
                    if ($itemCounts[$itemType] < $maxShields) {
                        $itemCounts[$itemType]++;
                        $coolerSegmentsUsage[$itemType] += Arr::get($resourceNetwork, 'Usage.Coolant.Maximum', 0.0);
                        $powerSegmentsUsage[$itemType] += Arr::get($resourceNetwork, 'Usage.Power.Maximum', 0.0);
                    }
                } elseif ($isWeapon) {
                    $coolerSegmentsUsage[$itemType] += Arr::get($resourceNetwork, 'Usage.Coolant.Maximum', 0.0);
                    $powerSegmentsUsage[$itemType] += Arr::get($resourceNetwork, 'Usage.Power.Maximum', 0.0);
                    $weaponPowerSegmentsUncapped += Arr::get($resourceNetwork, 'Usage.Power.Maximum', 0.0);
                } elseif ($isFlightController) {
                    $powerSegmentsUsage[$itemType] += Arr::get($resourceNetwork, 'Usage.Power.Maximum', 0.0);
                } else {
                    if (! $isCooler && ! $isGenerator) {
                        $coolerSegmentsUsage[$itemType] += Arr::get($resourceNetwork, 'Usage.Power.Maximum', 0.0);
                    } else {
                        $coolantGenerationSegments += Arr::get($resourceNetwork, 'Generation.Coolant', 0.0);
                    }

                    if (! $isGenerator) {
                        $powerSegmentsUsage[$itemType] += Arr::get($resourceNetwork, 'Usage.Power.Maximum', 0.0);
                    }
                }

                $powerGenerationSegments += Arr::get($resourceNetwork, 'Generation.Power', 0.0);
                $irTotal += $this->firstNumeric($stdItem, ['Emission.Ir', 'Emission.IR']) ?? 0.0;

            }

            if ($itemType === 'Armor') {
                $armorIrMultiplier *= (float) Arr::get($stdItem ?? [], 'Armor.SignalMultipliers.Infrared', 1.0);
                $armorEmMultiplier *= (float) Arr::get($stdItem ?? [], 'Armor.SignalMultipliers.Electromagnetic', 1.0);
            }
        }

        $availablePowerSegments = $this->availablePowerSegmentsFromPlants($powerPlants);
        if ($availablePowerSegments === null && $powerGenerationSegments > 0) {
            $availablePowerSegments = (int) round($powerGenerationSegments);
        }

        $powerSegmentsUsage['WeaponGun'] = min($weaponPoolMax ?? $powerSegmentsUsage['WeaponGun'], $powerSegmentsUsage['WeaponGun']);
        $coolerSegmentsUsage['WeaponGun'] = 0; // min($weaponPoolMax ?? $coolerSegmentsUsage['weapons'], $coolerSegmentsUsage['weapons']);

        // Scale weapon EM by pool limit ratio
        if ($weaponPoolMax !== null && $weaponPowerSegmentsUncapped > $weaponPoolMax) {
            $emGroups['WeaponGun'] *= ($weaponPoolMax / $weaponPowerSegmentsUncapped);
        }

        $powerSegmentsUsage = collect($powerSegmentsUsage)->filter(fn ($value, $key) => ! str_contains($key, 'Thruster'));
        $coolerSegmentsUsage = collect($coolerSegmentsUsage)->filter(fn ($value, $key) => ! str_contains($key, 'Thruster'));

        $coolerSegmentsUsageShields = $coolerSegmentsUsage->filter(fn ($value, $key) => $key !== 'QuantumDrive')->sum();
        $coolerSegmentsUsageQuantum = $coolerSegmentsUsage->filter(fn ($value, $key) => $key !== 'Shield')->sum();

        $powerSegmentsUsageShields = $powerSegmentsUsage->filter(fn ($value, $key) => $key !== 'QuantumDrive')->sum();
        $powerSegmentsUsageQuantum = $powerSegmentsUsage->filter(fn ($value, $key) => $key !== 'Shield')->sum();

        $coolerSegmentsUsageShields += $powerSegmentsUsageShields;
        $coolerSegmentsUsageQuantum += $powerSegmentsUsageQuantum;

        $emPerSegment = ($itemCounts['PowerPlant'] > 0 && $availablePowerSegments > 0)
            ? ($powerGeneratorEM / $availablePowerSegments)
            : 0.0;

        // Cooling load
        $coolingUsageShieldsPct = ($coolantGenerationSegments > 0) ? (($coolerSegmentsUsageShields) / $coolantGenerationSegments) : 0.0;
        $coolingUsageQuantumPct = ($coolantGenerationSegments > 0) ? (($coolerSegmentsUsageQuantum) / $coolantGenerationSegments) : 0.0;

        $irTotal *= $armorIrMultiplier;

        $irTotalShields = round($irTotal * $coolingUsageShieldsPct);
        $irTotalQuantum = round($irTotal * $coolingUsageQuantumPct);

        $emGroups = collect($emGroups)
            ->filter(fn ($value, $key) => ! str_contains($key, 'Thruster'))
            ->map(fn ($v) => $v * $armorEmMultiplier);

        return [
            'emission' => [
                'ir_shields' => $irTotalShields > 0 ? round($irTotalShields) : null,
                'ir_quantum' => $irTotalQuantum > 0 ? round($irTotalQuantum) : null,
                'em_shields' => collect([
                    ...$emGroups->filter(fn ($value, $key) => $key !== 'QuantumDrive' && $key !== 'PowerPlant' && $value > 0)->map(fn ($v) => round($v)),
                    'power_plants' => round($emPerSegment * $powerSegmentsUsageShields * $armorEmMultiplier),
                ])->sum(),
                'em_quantum' => collect([
                    ...$emGroups->filter(fn ($value, $key) => $key !== 'Shield' && $key !== 'PowerPlant' && $value > 0)->map(fn ($v) => round($v)),
                    'power_plants' => round($emPerSegment * $powerSegmentsUsageShields * $armorEmMultiplier),
                ])->sum(),
                'em_groups_quantum' => [
                    ...$emGroups->filter(fn ($value, $key) => $key !== 'Shield' && $key !== 'PowerPlant' && $value > 0)->map(fn ($v) => round($v)),
                    'power_plants' => round($emPerSegment * $powerSegmentsUsageShields * $armorEmMultiplier),
                ],
                'em_groups_shields' => [
                    ...$emGroups->filter(fn ($value, $key) => $key !== 'QuantumDrive' && $key !== 'PowerPlant' && $value > 0)->map(fn ($v) => round($v)),
                    'power_plants' => round($emPerSegment * $powerSegmentsUsageShields * $armorEmMultiplier),
                ],
                'em_segment_groups_quantum' => $powerSegmentsUsage->filter(fn ($value, $key) => $value > 0 && $key !== 'QuantumDrive'),
                'em_segment_groups_shields' => $powerSegmentsUsage->filter(fn ($value, $key) => $value > 0 && $key !== 'Shield'),
                'em_per_segment' => $emPerSegment,
            ],

            'power' => [
                'used_segments_shields' => $powerSegmentsUsageShields,
                'used_segments_quantum' => $powerSegmentsUsageQuantum,
                'generation_segments' => ($availablePowerSegments !== null && $availablePowerSegments > 0) ? $availablePowerSegments : null,
                'usage' => $powerSegmentsUsage->filter(fn ($value, $key) => $value > 0)->toArray(),
            ],

            'power_pools' => $powerPools,

            'cooling' => [
                'cooling_capacity' => $coolantGenerationSegments,
                'cooling_usage_shields_pct' => round($coolingUsageShieldsPct * 100, 2),
                'cooling_usage_quantum_pct' => round($coolingUsageQuantumPct * 100, 2),
                'coolerSegmentsUsageShields' => [...$coolerSegmentsUsage->filter(fn ($value, $key) => $key !== 'QuantumDrive' && $value > 0)->toArray(), 'PowerPlant' => $powerSegmentsUsageShields],
                'coolerSegmentsUsageQuantum' => [...$coolerSegmentsUsage->filter(fn ($value, $key) => $key !== 'Shield' && $value > 0)->toArray(), 'PowerPlant' => $powerSegmentsUsageQuantum],
            ],
        ];
    }

    public function getPriority(): int
    {
        return 40;
    }

    /**
     * multi-powerplant segments:
     * Segments = Σ round(Xi / n) + (n-1) * Σ Si
     *
     * @param  array<int, array{segments: float, size: int}>  $plants
     */
    private function availablePowerSegmentsFromPlants(array $plants): ?int
    {
        $n = count($plants);
        if ($n === 0) {
            return null;
        }

        $base = 0;
        $sizeSum = 0;

        foreach ($plants as $p) {
            $seg = (float) ($p['segments'] ?? 0.0);
            if ($seg <= 0) {
                continue;
            }
            $base += (int) round($seg / $n);
            $sizeSum += (int) ($p['size'] ?? 0);
        }

        if ($base <= 0) {
            return null;
        }

        $bonus = ($n - 1) * $sizeSum;

        return $base + $bonus;
    }

    private function firstNumeric(array $data, array $paths): ?float
    {
        foreach ($paths as $path) {
            $value = Arr::get($data, $path);
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }
}
