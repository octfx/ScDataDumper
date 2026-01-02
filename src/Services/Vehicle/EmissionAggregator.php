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

        // For budgeting
        $shieldPowerMinTotal = 0.0;
        $shieldCoolantMinTotal = 0.0;
        $shieldCoolantMaxTotal = 0.0;
        $shieldCount = 0;

        foreach ($this->walker->walkItems($loadout) as $entry) {
            $item = $entry['Item'];

            if ($item === null) {
                continue;
            }

            $itemType = $item['type'];

            if (! isset($itemCounts[$itemType])) {
                $itemCounts[$itemType] = 0;
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

                        $shieldCount++;
                        $shieldPowerMinTotal += (float) Arr::get($resourceNetwork, 'Usage.Power.Minimum', 0.0);
                        $shieldCoolantMinTotal += (float) Arr::get($resourceNetwork, 'Usage.Coolant.Minimum', 0.0);
                        $shieldCoolantMaxTotal += (float) Arr::get($resourceNetwork, 'Usage.Coolant.Maximum', 0.0);

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

        $powerSegmentsUsage['WeaponGun'] = min($weaponPoolMax ?? $powerSegmentsUsage['WeaponGun'] ?? 0, $powerSegmentsUsage['WeaponGun'] ?? 0);
        $coolerSegmentsUsage['WeaponGun'] = 0; // min($weaponPoolMax ?? $coolerSegmentsUsage['weapons'], $coolerSegmentsUsage['weapons']);

        // Scale weapon EM by pool limit ratio
        if ($weaponPoolMax !== null && $weaponPowerSegmentsUncapped > $weaponPoolMax) {
            $emGroups['WeaponGun'] *= ($weaponPoolMax / $weaponPowerSegmentsUncapped);
        }

        $powerSegmentsUsage = collect($powerSegmentsUsage)->filter(fn ($value, $key) => ! str_contains($key, 'Thruster'));
        $coolerSegmentsUsage = collect($coolerSegmentsUsage)->filter(fn ($value, $key) => ! str_contains($key, 'Thruster'));

        // Cooling usage base values (before adding power usage)
        $coolerSegmentsUsageShieldsBase = $coolerSegmentsUsage->filter(fn ($value, $key) => $key !== 'QuantumDrive')->sum();
        $coolerSegmentsUsageQuantumBase = $coolerSegmentsUsage->filter(fn ($value, $key) => $key !== 'Shield')->sum();

        $powerSegmentsUsageShields = $powerSegmentsUsage->filter(fn ($value, $key) => $key !== 'QuantumDrive')->sum();
        $powerSegmentsUsageQuantum = $powerSegmentsUsage->filter(fn ($value, $key) => $key !== 'Shield')->sum();

        // Total cooling load includes base coolant usage plus power usage
        $coolerSegmentsUsageShieldsTotal = $coolerSegmentsUsageShieldsBase + $powerSegmentsUsageShields;
        $coolerSegmentsUsageQuantumTotal = $coolerSegmentsUsageQuantumBase + $powerSegmentsUsageQuantum;

        $emPerSegment = (($itemCounts['PowerPlant'] ?? 0) > 0 && $availablePowerSegments > 0)
            ? ($powerGeneratorEM / $availablePowerSegments)
            : 0.0;

        // Cooling load
        $coolingUsageShieldsPct = ($coolantGenerationSegments > 0) ? (($coolerSegmentsUsageShieldsTotal) / $coolantGenerationSegments) : 0.0;
        $coolingUsageQuantumPct = ($coolantGenerationSegments > 0) ? (($coolerSegmentsUsageQuantumTotal) / $coolantGenerationSegments) : 0.0;

        $irTotal *= $armorIrMultiplier;

        $irTotalShields = round($irTotal * $coolingUsageShieldsPct);
        $irTotalQuantum = round($irTotal * $coolingUsageQuantumPct);

        $emGroups = collect($emGroups)
            ->filter(fn ($value, $key) => ! str_contains($key, 'Thruster'))
            ->map(fn ($v) => $v * $armorEmMultiplier)
            ->put('PowerPlant', round($emPerSegment * $powerSegmentsUsageShields * $armorEmMultiplier));

        $emGroupsShields = $emGroups->filter(fn ($value, $key) => $key !== 'QuantumDrive' && $value > 0)->map(fn ($v) => round($v));
        $emGroupsQuantum = $emGroups->filter(fn ($value, $key) => $key !== 'Shield' && $value > 0)->map(fn ($v) => round($v));

        // WIP Power budget
        $powerBudgeting = null;

        if ($availablePowerSegments !== null && $availablePowerSegments > 0) {
            $usageByTypeShields = $powerSegmentsUsage
                ->filter(fn ($value, $key) => $key !== 'QuantumDrive' && $value > 0)
                ->toArray();

            $usageByTypeQuantum = $powerSegmentsUsage
                ->filter(fn ($value, $key) => $key !== 'Shield' && $value > 0)
                ->toArray();

            $budgetShields = $this->applyPowerBudget(
                $usageByTypeShields,
                $availablePowerSegments,
                $shieldPowerMinTotal
            );

            $budgetQuantum = $this->applyPowerBudget(
                $usageByTypeQuantum,
                $availablePowerSegments,
                0.0
            );

            $budgetedShieldPower = (float) ($budgetShields['budgeted_usage_by_type']['Shield'] ?? 0.0);

            $budgetedShieldCoolant = $this->budgetedShieldCoolantUsage(
                $budgetedShieldPower,
                $shieldPowerMinTotal,
                $shieldCoolantMinTotal,
                $shieldCoolantMaxTotal
            );

            $coolerSegmentsUsageShieldsBaseExclShieldCoolant = max(0.0, $coolerSegmentsUsageShieldsBase - $shieldCoolantMaxTotal);

            $budgetedCoolerSegmentsUsageShieldsTotal =
                $coolerSegmentsUsageShieldsBaseExclShieldCoolant
                + $budgetedShieldCoolant
                + (float) $budgetShields['budgeted_used_segments'];

            $budgetedCoolingUsageShieldsPct = ($coolantGenerationSegments > 0)
                ? ($budgetedCoolerSegmentsUsageShieldsTotal / $coolantGenerationSegments)
                : 0.0;

            $budgetedCoolerSegmentsUsageQuantumTotal = ($coolantGenerationSegments > 0)
                ? ($coolerSegmentsUsageQuantumTotal - $powerSegmentsUsageQuantum + (float) $budgetQuantum['budgeted_used_segments'])
                : 0.0;

            $budgetedCoolingUsageQuantumPct = ($coolantGenerationSegments > 0)
                ? ($budgetedCoolerSegmentsUsageQuantumTotal / $coolantGenerationSegments)
                : 0.0;

            $irTotalShieldsBudgeted = round($irTotal * $budgetedCoolingUsageShieldsPct);
            $irTotalQuantumBudgeted = round($irTotal * $budgetedCoolingUsageQuantumPct);

            $powerPlantEmBudgetedShields = round($emPerSegment * (float) $budgetShields['budgeted_used_segments'] * $armorEmMultiplier);
            $powerPlantEmBudgetedQuantum = round($emPerSegment * (float) $budgetQuantum['budgeted_used_segments'] * $armorEmMultiplier);

            $emGroupsShieldsBudgeted = $emGroupsShields->toArray();
            $emGroupsQuantumBudgeted = $emGroupsQuantum->toArray();

            $emGroupsShieldsBudgeted['PowerPlant'] = $powerPlantEmBudgetedShields;
            $emGroupsQuantumBudgeted['PowerPlant'] = $powerPlantEmBudgetedQuantum;

            $powerBudgeting = [
                'available_segments' => $availablePowerSegments,
                'minimums' => [
                    'flight_controller_min_segments' => 1.0,
                    'shield_min_power_segments' => $shieldPowerMinTotal,
                    'shield_min_coolant_segments' => $shieldCoolantMinTotal,
                    'weapon_gun_min_segments' => 1.0,
                    'shield_count' => $shieldCount,
                ],
                'shields' => [
                    'original_used_segments' => $powerSegmentsUsageShields,
                    'budgeted_used_segments' => $budgetShields['budgeted_used_segments'],
                    'over_budget_segments' => $budgetShields['over_budget_segments'],
                    'remaining_over_budget_segments' => $budgetShields['remaining_over_budget_segments'],
                    'original_usage_by_type' => $budgetShields['original_usage_by_type'],
                    'budgeted_usage_by_type' => $budgetShields['budgeted_usage_by_type'],
                    'reductions_by_type' => $budgetShields['reductions_by_type'],

                    'original_shield_coolant_segments' => $shieldCoolantMaxTotal,
                    'budgeted_shield_coolant_segments' => $budgetedShieldCoolant,
                    'budgeted_total_cooling_segments' => $budgetedCoolerSegmentsUsageShieldsTotal,

                    'ir_shields_budgeted' => $irTotalShieldsBudgeted > 0 ? (int) round($irTotalShieldsBudgeted) : null,
                    'cooling_usage_shields_pct_budgeted' => round($budgetedCoolingUsageShieldsPct * 100, 2),
                    'em_shields_budgeted' => array_sum($emGroupsShieldsBudgeted),
                    'em_groups_shields_budgeted' => $emGroupsShieldsBudgeted,
                ],
                'quantum' => [
                    'original_used_segments' => $powerSegmentsUsageQuantum,
                    'budgeted_used_segments' => $budgetQuantum['budgeted_used_segments'],
                    'over_budget_segments' => $budgetQuantum['over_budget_segments'],
                    'remaining_over_budget_segments' => $budgetQuantum['remaining_over_budget_segments'],
                    'original_usage_by_type' => $budgetQuantum['original_usage_by_type'],
                    'budgeted_usage_by_type' => $budgetQuantum['budgeted_usage_by_type'],
                    'reductions_by_type' => $budgetQuantum['reductions_by_type'],
                    'ir_quantum_budgeted' => $irTotalQuantumBudgeted > 0 ? (int) round($irTotalQuantumBudgeted) : null,
                    'cooling_usage_quantum_pct_budgeted' => round($budgetedCoolingUsageQuantumPct * 100, 2),
                    'em_quantum_budgeted' => array_sum($emGroupsQuantumBudgeted),
                    'em_groups_quantum_budgeted' => $emGroupsQuantumBudgeted,
                ],
            ];
        }

        return [
            'emission' => [
                'ir_shields' => $irTotalShields > 0 ? round($irTotalShields) : null,
                'ir_quantum' => $irTotalQuantum > 0 ? round($irTotalQuantum) : null,
                'em_shields' => $emGroupsShields->sum(),
                'em_quantum' => $emGroupsQuantum->sum(),
                'em_groups_shields' => $emGroupsShields->toArray(),
                'em_groups_quantum' => $emGroupsQuantum->toArray(),
                'em_segment_groups_shields' => $powerSegmentsUsage->filter(fn ($value, $key) => $value > 0 && $key !== 'Shield'),
                'em_segment_groups_quantum' => $powerSegmentsUsage->filter(fn ($value, $key) => $value > 0 && $key !== 'QuantumDrive'),
                'em_per_segment' => $emPerSegment,
            ],

            'power' => [
                'used_segments_shields' => $powerSegmentsUsageShields,
                'used_segments_quantum' => $powerSegmentsUsageQuantum,
                'generation_segments' => ($availablePowerSegments !== null && $availablePowerSegments > 0) ? $availablePowerSegments : null,
                'usage' => $powerSegmentsUsage->filter(fn ($value, $key) => $value > 0)->toArray(),
            ],

            'power_budgeting' => $powerBudgeting,

            'power_pools' => $powerPools,

            'cooling' => [
                'generation_segments' => $coolantGenerationSegments,
                'usage_shields_pct' => round($coolingUsageShieldsPct * 100, 2),
                'usage_quantum_pct' => round($coolingUsageQuantumPct * 100, 2),
                'used_segments_shields' => [...$coolerSegmentsUsage->filter(fn ($value, $key) => $key !== 'QuantumDrive' && $value > 0)->toArray(), 'PowerPlant' => $powerSegmentsUsageShields],
                'used_segments_quantum' => [...$coolerSegmentsUsage->filter(fn ($value, $key) => $key !== 'Shield' && $value > 0)->toArray(), 'PowerPlant' => $powerSegmentsUsageQuantum],
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

    /**
     * power budgeting when usage exceeds available segments
     *
     * Priority reductions:
     * 1) FlightController down to min 1
     * 2) Shield down to min = $shieldMinTotal
     * 3) WeaponGun down to min 1
     *
     * @param  array<string, float|int>  $usageByType
     * @return array{
     *   original_usage_by_type: array<string, float>,
     *   budgeted_usage_by_type: array<string, float>,
     *   reductions_by_type: array<string, float>,
     *   over_budget_segments: float,
     *   remaining_over_budget_segments: float,
     *   budgeted_used_segments: float
     * }
     */
    private function applyPowerBudget(array $usageByType, int $availableSegments, float $shieldMinTotal): array
    {
        $original = [];
        $adjusted = [];
        foreach ($usageByType as $k => $v) {
            $original[$k] = (float) $v;
            $adjusted[$k] = (float) $v;
        }

        $originalUsed = array_sum($original);
        $over = $originalUsed - (float) $availableSegments;

        if ($over <= 0.0) {
            return [
                'original_usage_by_type' => $original,
                'budgeted_usage_by_type' => $adjusted,
                'reductions_by_type' => [],
                'over_budget_segments' => 0.0,
                'remaining_over_budget_segments' => 0.0,
                'budgeted_used_segments' => $originalUsed,
            ];
        }

        $reductions = [];

        $over = $this->reduceUsageKey($adjusted, 'FlightController', $over, 1.0, $reductions);
        $over = $this->reduceUsageKey($adjusted, 'Shield', $over, max(0.0, $shieldMinTotal), $reductions);
        $over = $this->reduceUsageKey($adjusted, 'WeaponGun', $over, 1.0, $reductions);

        foreach ($adjusted as $k => $v) {
            if (abs($v) < 1e-9) {
                $adjusted[$k] = 0.0;
            }
        }

        $mins = [
            'FlightController' => 1.0,
            'WeaponGun' => 1.0,
        ];
        if ($shieldMinTotal > 0.0) {
            $mins['Shield'] = $shieldMinTotal;
        }

        foreach ($adjusted as $k => $v) {
            $min = $mins[$k] ?? 0.0;
            $floored = floor((float) $v);
            $adjusted[$k] = ($floored < $min) ? $min : $floored;
        }

        return [
            'original_usage_by_type' => $original,
            'budgeted_usage_by_type' => $adjusted,
            'reductions_by_type' => $reductions,
            'over_budget_segments' => max(0.0, $originalUsed - (float) $availableSegments),
            'remaining_over_budget_segments' => max(0.0, $over),
            'budgeted_used_segments' => array_sum($adjusted),
        ];
    }

    /**
     * @param  array<string, float>  $usageByType
     * @param  array<string, float>  $reductions
     */
    private function reduceUsageKey(array &$usageByType, string $key, float $over, float $minValue, array &$reductions): float
    {
        if ($over <= 0.0) {
            return 0.0;
        }

        if (! array_key_exists($key, $usageByType)) {
            return $over;
        }

        $current = (float) $usageByType[$key];
        $minValue = max(0.0, $minValue);

        if ($current <= $minValue) {
            return $over;
        }

        $reducible = $current - $minValue;
        $delta = min($reducible, $over);

        $usageByType[$key] = $current - $delta;
        $reductions[$key] = ($reductions[$key] ?? 0.0) + $delta;

        return $over - $delta;
    }

    /**
     * Budgeted shield coolant:
     * Uses Usage.Coolant.Minimum scaled by the *shield power budget*.
     *
     * Current implementation assumes coolant scales linearly from the minimum based on how much
     * shield power you have allocated, and clamps to the observed MAX coolant usage
     */
    private function budgetedShieldCoolantUsage(
        float $budgetedShieldPower,
        float $shieldPowerMinTotal,
        float $shieldCoolantMinTotal,
        float $shieldCoolantMaxTotal
    ): float {
        if ($budgetedShieldPower <= 0.0) {
            return 0.0;
        }

        if ($shieldPowerMinTotal > 0.0 && $shieldCoolantMinTotal > 0.0) {
            $coolant = $shieldCoolantMinTotal * ($budgetedShieldPower / $shieldPowerMinTotal);

            if ($shieldCoolantMaxTotal > 0.0) {
                $coolant = min($shieldCoolantMaxTotal, $coolant);
            }

            return max(0.0, $coolant);
        }

        return max(0.0, $shieldCoolantMaxTotal);
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
