<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use Illuminate\Support\Arr;
use Octfx\ScDataDumper\Helper\VehicleWrapper;

/**
 * Aggregates emissions and fuel-related metrics by traversing installed items.
 */
final readonly class VehicleMetricsAggregator
{
    public function __construct(
        private EquippedItemWalker $walker,
        private VehicleWrapper $wrapper,
    ) {}

    /**
     * @param  array  $loadout  Nested loadout entries from VehicleWrapper
     * @return array{
     *   emission: array{ir: float|null, em: float|null, em_with_shields: float|null, em_with_quantum: float|null},
     *   fuel_capacity: float|null,
     *   quantum_fuel_capacity: float|null,
     *   fuel_intake_rate: float|null,
     *   fuel_usage: array<string, float>
     * }
     */
    public function aggregate(array $loadout): array
    {
        $pools = $this->wrapper->entity->get('Components/SItemPortContainerComponentParams/resourceNetworkPowerPools/itemPools');

        $powerPools = [];
        $weaponPoolMax = null;
        $maxShields = 0;
        foreach ($pools?->children() ?? [] as $pool) {
            $itemType = $pool->get('@itemType');

            if ($itemType === 'WeaponGun') {
                $weaponPoolMax = $pool->get('@poolSize');
            }

            if ($itemType === 'Shield') {
                $maxShields = $pool->get('@maxItemCount');
            }

            $powerPools[$itemType] = [
                'type' => $pool->get('@__polymorphicType'),
                'item_type' => $itemType,
                'size' => $pool->get('@poolSize') ?? $pool->get('@maxItemCount'),
            ];
        }

        // Emission accumulators
        $irTotal = 0.0;

        $itemCounts = [
            'coolers' => 0,
            'shields' => 0,
            'quantum' => 0,
            'powerplants' => 0,
            'weapons' => 0,
        ];

        $em = [
            'coolers' => 0.0,
            'shields' => 0.0,
            'quantum' => 0.0,
            'weapons' => 0.0,
            'powerplants' => 0.0,
            'rest' => 0.0,
        ];

        // Power (segments) & cooling
        $powerSegmentsUsage = [
            'shields' => 0.0,
            'quantum' => 0.0,
            'weapons' => 0.0,
            'flightcontrollers' => 0.0,
            'rest' => 0.0,
        ];

        $powerGenerationSegments = 0.0; // raw sum of Generation.Power across powerplants
        $powerGeneratorEM = 0.0;

        /** @var array<int, array{segments: float, size: int}> $powerPlants */
        $powerPlants = [];

        $coolingCapacity = 0.0;
        $coolerSegmentsUsage = [
            'shields' => 0.0,
            'quantum' => 0.0,
            'weapons' => 0.0,
            'rest' => 0.0,
        ];

        $coolantGenerationSegments = 0.0; // Max draw from ResourceNetwork.Usage.Power.Maximum if present
        $weaponSegmentsRaw = 0.0;

        // Other ship metrics
        $fuelCapacity = 0.0;
        $quantumFuelCapacity = 0.0;
        $fuelIntakeRate = 0.0;
        $shieldHp = 0.0;
        $shieldRegen = 0.0;
        $distortionPool = 0.0;
        $missileCount = 0.0;
        $missileRackAmmo = 0.0;
        $quantumFuelRate = 0.0;

        // Weapon storage
        $weaponLockers = 0;
        $weaponSlotsTotal = 0;
        $weaponSlotsRifle = 0;
        $weaponSlotsPistol = 0;

        /** @var array<int, array{name: string|null, class_name: string|null, port: string|null, slots_total: int, slots_rifle: int, slots_pistol: int}> */
        $weaponStorageByLocker = [];

        $armorIrMultiplier = 1.0;
        $armorEmMultiplier = 1.0;

        $fuelUsage = [
            'Main' => 0.0,
            'Retro' => 0.0,
            'Vtol' => 0.0,
            'Maneuvering' => 0.0,
        ];

        foreach ($this->walker->walk($loadout) as $entry) {
            $item = $entry['Item'];

            $stdItem = is_array($item['stdItem'] ?? null) ? $item['stdItem'] : null;

            $attachType = Arr::get($item, 'type') ?? Arr::get($stdItem ?? [], 'Type');
            $lowerType = strtolower((string) $attachType);

            $isShield = str_contains($lowerType, 'shield');
            $isQuantum = str_contains($lowerType, 'quantumdrive') || $attachType === 'QuantumDrive';
            $isGenerator = str_contains($lowerType, 'powerplant');

            $isCooler = str_contains($lowerType, 'cooler')
                || str_contains(strtolower((string) ($entry['portName'] ?? '')), 'cooler');

            $resourceNetwork = Arr::get($stdItem, 'ResourceNetwork') ?: null;

            if ($isGenerator) {
                $powerGeneratorEM += Arr::get($stdItem, 'Emission.Em.Maximum', 0.0);
            }

            if (is_array($resourceNetwork)) {
                // EM Aggregation
                if ($isQuantum) {
                    $em['quantum'] += Arr::get($stdItem, 'Emission.Em.Maximum', 0.0);
                    $itemCounts['quantum']++;
                } elseif ($isShield) {
                    if ($itemCounts['shields'] <= $maxShields) {
                        $em['shields'] += Arr::get($stdItem, 'Emission.Em.Maximum', 0.0);
                    }
                } elseif ($isCooler) {
                    $em['coolers'] += Arr::get($stdItem, 'Emission.Em.Maximum', 0.0);
                    $itemCounts['coolers']++;
                } elseif (str_starts_with(Arr::get($entry, 'Item.classification', ''), 'Ship.Turret') || str_starts_with(Arr::get($entry, 'Item.classification', ''), 'Ship.Weapon')) {
                    $em['weapons'] += Arr::get($stdItem, 'Emission.Em.Maximum', 0.0);
                    $itemCounts['weapons']++;
                    // $weaponSegmentsRaw += Arr::get($resourceNetwork, 'Usage.Power.Maximum', 0.0);
                } elseif ($isGenerator) {
                    $em['powerplants'] += Arr::get($stdItem, 'Emission.Em.Maximum', 0.0);
                    $itemCounts['powerplants']++;

                    // per-plant segments and size for multi-plant "max segments" calculation
                    $genSegments = (float) Arr::get($resourceNetwork, 'Generation.Power', 0.0);
                    $plantSize = Arr::get($entry, 'Item.size');
                    if ($genSegments > 0) {
                        $powerPlants[] = ['segments' => $genSegments, 'size' => $plantSize];
                    }
                } else {
                    $em['rest'] += Arr::get($stdItem, 'Emission.Em.Maximum', 0.0);
                }

                // Cooler and Power Usage
                if ($isQuantum) {
                    $coolerSegmentsUsage['quantum'] += Arr::get($resourceNetwork, 'Usage.Coolant.Maximum', 0.0);
                    $powerSegmentsUsage['quantum'] += Arr::get($resourceNetwork, 'Usage.Power.Maximum', 0.0);
                } elseif ($isShield) {
                    if ($itemCounts['shields'] <= $maxShields) {
                        $itemCounts['shields']++;
                        $coolerSegmentsUsage['shields'] += Arr::get($resourceNetwork, 'Usage.Coolant.Maximum', 0.0);
                        $powerSegmentsUsage['shields'] += Arr::get($resourceNetwork, 'Usage.Power.Maximum', 0.0);
                    }
                } elseif (str_starts_with(Arr::get($entry, 'Item.classification', ''), 'Ship.Turret') || str_starts_with(Arr::get($entry, 'Item.classification', ''), 'Ship.Weapon')) {
                    $coolerSegmentsUsage['weapons'] += Arr::get($resourceNetwork, 'Usage.Coolant.Maximum', 0.0);
                    $powerSegmentsUsage['weapons'] += Arr::get($resourceNetwork, 'Usage.Power.Maximum', 0.0);
                } elseif ($lowerType === 'flightcontroller') {
                    $powerSegmentsUsage['flightcontrollers'] += Arr::get($resourceNetwork, 'Usage.Power.Maximum', 0.0);
                } else {
                    if (! $isCooler && ! $isGenerator) {
                        $coolerSegmentsUsage['rest'] += Arr::get($resourceNetwork, 'Usage.Power.Maximum', 0.0);
                    } else {
                        $coolantGenerationSegments += Arr::get($resourceNetwork, 'Generation.Coolant', 0.0);
                    }

                    if (! $isGenerator) {
                        $powerSegmentsUsage['rest'] += Arr::get($resourceNetwork, 'Usage.Power.Maximum', 0.0);
                    }
                }

                // debug
                $powerGenerationSegments += Arr::get($resourceNetwork, 'Generation.Power', 0.0);

                $irTotal += $this->firstNumeric($stdItem, ['Emission.Ir', 'Emission.IR']) ?? 0.0;
            }

            if ($attachType === 'Armor') {
                $armorIrMultiplier *= (float) Arr::get($stdItem ?? [], 'Armor.SignalMultipliers.Infrared', 1.0);
                $armorEmMultiplier *= (float) Arr::get($stdItem ?? [], 'Armor.SignalMultipliers.Electromagnetic', 1.0);
            }

            // Fuel capacities
            if (in_array($attachType, ['FuelTank', 'ExternalFuelTank'], true)) {
                $fuelCapacity += $this->fuelCapacityFromItem($stdItem);
            } elseif ($attachType === 'QuantumFuelTank') {
                $quantumFuelCapacity += $this->fuelCapacityFromItem($stdItem);
            }

            // Quantum drive fuel requirement
            if ($attachType === 'QuantumDrive') {
                $quantumFuelRate = $this->firstNumeric($stdItem, [
                    'QuantumDrive.QuantumFuelRequirement',
                ]) ?? $quantumFuelRate;
            }

            $fuelIntakeRate += (float) (
                Arr::get($stdItem ?? [], 'FuelIntake.FlowRates.FuelPushRate', null)
                ?? Arr::get($stdItem ?? [], 'FuelIntake.FlowRates.fuelPushRate', 0.0)
            );

            // Thruster fuel usage grouped by thruster type
            $thrusterType = Arr::get($stdItem ?? [], 'Thruster.ThrusterType');
            if ($thrusterType !== null) {
                $usage = $this->thrusterFuelUsage($stdItem);
                $bucket = $this->mapThrusterType((string) $thrusterType);
                if ($bucket !== null) {
                    $fuelUsage[$bucket] += $usage;
                }
            }

            // Shields
            $shieldHp += (float) Arr::get($stdItem ?? [], 'Shield.MaxShieldHealth', 0.0);
            $shieldRegen += (float) Arr::get($stdItem ?? [], 'Shield.MaxShieldRegen', 0.0);

            // Distortion pool
            $distortionPool += (float) Arr::get($stdItem ?? [], 'Distortion.Maximum', 0.0);

            // Ammunition / missiles
            $type = Arr::get($stdItem ?? [], 'Type', Arr::get($item, 'Type'));
            if (is_string($type) && str_starts_with(strtolower($type), 'missile')) {
                $missileCount++;
            }

            // Missile rack capacity
            if (is_string($type) && str_starts_with(strtolower($type), 'missilelauncher')) {
                $missileRackAmmo += (float) Arr::get($stdItem ?? [], 'MissileRack.Count', 0.0);
            }

            // Weapon racks / lockers (capacity overview)
            $ports = $this->extractPorts($item);
            $className = strtolower((string) Arr::get($stdItem ?? [], 'ClassName', Arr::get($item, 'ClassName', Arr::get($item, 'className', ''))));

            $isWeaponLocker = str_contains($className, 'weapon_rack') || str_contains($className, 'weapon_locker');

            $lockerSlotsTotal = 0;
            $lockerSlotsRifle = 0;
            $lockerSlotsPistol = 0;

            foreach ($ports as $port) {
                $types = $port['Types'] ?? [];
                if ($this->isWeaponPersonalPort($types)) {
                    $isWeaponLocker = true;

                    $maxSize = $port['MaxSize'] ?? $port['Size'] ?? null;

                    $weaponSlotsTotal++;
                    $lockerSlotsTotal++;

                    if ($maxSize !== null && (float) $maxSize <= 2) {
                        $weaponSlotsPistol++;
                        $lockerSlotsPistol++;
                    } else {
                        $weaponSlotsRifle++;
                        $lockerSlotsRifle++;
                    }
                }
            }

            if ($isWeaponLocker) {
                $weaponLockers++;

                $weaponStorageByLocker[] = [
                    'name' => Arr::get($stdItem ?? [], 'Name')
                        ?? Arr::get($item, 'name')
                            ?? Arr::get($item, 'Name'),
                    'class_name' => $className !== '' ? $className : null,
                    'port' => is_string($entry['portName'] ?? null) ? $entry['portName'] : null,
                    'slots_total' => $lockerSlotsTotal,
                    'slots_rifle' => $lockerSlotsRifle,
                    'slots_pistol' => $lockerSlotsPistol,
                ];
            }
        }

        $availablePowerSegments = $this->availablePowerSegmentsFromPlants($powerPlants);
        if ($availablePowerSegments === null && $powerGenerationSegments > 0) {
            // single-plant or missing sizes
            $availablePowerSegments = (int) round($powerGenerationSegments);
        }

        $powerSegmentsUsage['weapons'] = min($weaponPoolMax ?? $powerSegmentsUsage['weapons'], $powerSegmentsUsage['weapons']);

        $powerSegmentsUsage['weapons'] = min($weaponPoolMax ?? $powerSegmentsUsage['weapons'], $powerSegmentsUsage['weapons']);
        $coolerSegmentsUsage['weapons'] = 0; // min($weaponPoolMax ?? $coolerSegmentsUsage['weapons'], $coolerSegmentsUsage['weapons']);

        $powerSegmentsUsage = collect($powerSegmentsUsage);
        $coolerSegmentsUsage = collect($coolerSegmentsUsage);

        $coolerSegmentsUsageShields = $coolerSegmentsUsage->filter(fn ($value, $key) => $key !== 'quantum')->sum();
        $coolerSegmentsUsageQuantum = $coolerSegmentsUsage->filter(fn ($value, $key) => $key !== 'shields')->sum();

        $powerSegmentsUsageShields = $powerSegmentsUsage->filter(fn ($value, $key) => $key !== 'quantum')->sum();
        $powerSegmentsUsageQuantum = $powerSegmentsUsage->filter(fn ($value, $key) => $key !== 'shields')->sum();

        $coolerSegmentsUsageShields += $powerSegmentsUsageShields;
        $coolerSegmentsUsageQuantum += $powerSegmentsUsageQuantum;

        $emPerSegment = ($itemCounts['powerplants'] > 0 && $availablePowerSegments > 0)
            ? ($powerGeneratorEM / $availablePowerSegments)
            : 0.0;

        // Cooling load
        $coolingUsageShieldsPct = ($coolantGenerationSegments > 0) ? ($coolerSegmentsUsageShields / $coolantGenerationSegments) : 0.0;
        $coolingUsageQuantumPct = ($coolantGenerationSegments > 0) ? ($coolerSegmentsUsageQuantum / $coolantGenerationSegments) : 0.0;

        $irTotal *= $armorIrMultiplier;

        $irTotalShields = round($irTotal * $coolingUsageShieldsPct);
        $irTotalQuantum = round($irTotal * $coolingUsageQuantumPct);

        $em = collect($em);

        $emShields = $em->filter(fn ($value, $key) => $key !== 'quantum' && $key !== 'powerplants')
            ->map(fn ($v) => $v)
            ->push($emPerSegment * $powerSegmentsUsageShields)
            ->sum();

        $emQuantum = $em->filter(fn ($value, $key) => $key !== 'shields' && $key !== 'powerplants')
            ->map(fn ($v) => $v)
            ->push($emPerSegment * $powerSegmentsUsageQuantum)
            ->sum();

        return [
            'emission' => [
                'ir_shields' => $irTotalShields > 0 ? round($irTotalShields) : null,
                'ir_quantum' => $irTotalQuantum > 0 ? round($irTotalQuantum) : null,
                'em_shields' => $emShields > 0 ? round($emShields * $armorEmMultiplier) : null,
                'em_quantum' => $emQuantum > 0 ? round($emQuantum * $armorEmMultiplier) : null,
                'em_groups_quantum' => [
                    ...$em->filter(fn ($value, $key) => $key !== 'shields' && $key !== 'powerplants')->map(fn ($v) => $v * $armorEmMultiplier)->map(fn ($v) => round($v)),
                    'power_plants' => round($emPerSegment * $powerSegmentsUsageQuantum * $armorEmMultiplier),
                ],
                'em_groups_shields' => [
                    ...$em->filter(fn ($value, $key) => $key !== 'quantum' && $key !== 'powerplants')->map(fn ($v) => $v * $armorEmMultiplier)->map(fn ($v) => round($v)),
                    'power_plants' => round($emPerSegment * $powerSegmentsUsageShields * $armorEmMultiplier),
                ],
            ],

            'fuel_capacity' => $fuelCapacity > 0 ? $fuelCapacity : null,
            'quantum_fuel_capacity' => $quantumFuelCapacity > 0 ? $quantumFuelCapacity : null,
            'fuel_intake_rate' => $fuelIntakeRate > 0 ? $fuelIntakeRate : null,
            'fuel_usage' => $fuelUsage,

            'cooling' => [
                'cooling_capacity' => $coolantGenerationSegments - 1,
                'cooling_usage_shields_pct' => round($coolingUsageShieldsPct * 100, 2),
                'cooling_usage_quantum_pct' => round($coolingUsageQuantumPct * 100, 2),
                'cooling_rate' => $coolingCapacity > 0 ? $coolingCapacity : null,
            ],

            'power' => [
                'used_segments_shields' => $powerSegmentsUsageShields,
                'used_segments_quantum' => $powerSegmentsUsageQuantum,
                'generation_segments' => ($availablePowerSegments !== null && $availablePowerSegments > 0) ? $availablePowerSegments : null,
            ],

            'power_pools' => $powerPools,

            'shields' => [
                'hp' => $shieldHp > 0 ? $shieldHp : null,
                'regen' => $shieldRegen > 0 ? $shieldRegen * 0.66 : null,
            ],

            'distortion' => [
                'pool' => $distortionPool > 0 ? $distortionPool : null,
            ],

            'ammo' => [
                'missiles' => $missileCount > 0 ? $missileCount : null,
                'missile_rack_capacity' => $missileRackAmmo > 0 ? $missileRackAmmo : null,
            ],

            'weapon_storage' => [
                'lockers' => $weaponLockers > 0 ? $weaponLockers : null,

                'slots_total' => $weaponSlotsTotal > 0 ? $weaponSlotsTotal : null,
                'slots_rifle' => $weaponSlotsRifle > 0 ? $weaponSlotsRifle : null,
                'slots_pistol' => $weaponSlotsPistol > 0 ? $weaponSlotsPistol : null,

                'by_locker' => $weaponStorageByLocker !== [] ? $weaponStorageByLocker : null,
            ],
        ];
    }

    /**
     * multi-powerplant segments:
     * Segments = Σ round(Xi / n) + (n-1) * Σ Si
     *
     * Xi = per-plant Generation.Power (single-plant segments)
     * Si = per-plant size integer (S0->0, S1->1, S2->2, ...)
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
     * Fuel capacity from either stdItem (ResourceContainer.Capacity.*) or old Components.
     */
    private function fuelCapacityFromItem(array $data): float
    {
        $scu = $this->firstNumeric($data, [
            'ResourceContainer.Capacity.SCU',
        ]) ?? 0.0;

        return $scu * 1000;
    }

    private function thrusterFuelUsage(array $data): float
    {
        $burnPer10k = $this->firstNumeric($data, [
            'Thruster.FuelBurnRatePer10KNewton',
        ]) ?? 0.0;

        $thrust = $this->firstNumeric($data, [
            'Thruster.ThrustCapacity',
        ]) ?? 0.0;

        return ($burnPer10k / 1e4) * $thrust;
    }

    private function mapThrusterType(string $type): ?string
    {
        return match (strtolower($type)) {
            'main' => 'Main',
            'retro' => 'Retro',
            'vtol' => 'Vtol',
            'maneuvering', 'maneuver', 'maneuver_thruster', 'maneuverthrust', 'maneuverthruster' => 'Maneuvering',
            default => null,
        };
    }

    /**
     * Returns the first numeric value found for the provided paths.
     */
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

    /**
     * Normalize port definitions from either stdItem or raw Components.
     */
    private function extractPorts(array $item): array
    {
        $ports = Arr::get($item, 'stdItem.Ports', []);
        if (is_array($ports) && $ports !== []) {
            return $ports;
        }

        $rawPorts = Arr::get($item, 'Components.SItemPortContainerComponentParams.Ports', []);
        if ($rawPorts === [] || ! is_array($rawPorts)) {
            return [];
        }

        $rawPorts = $rawPorts['SItemPortDef'] ?? $rawPorts;

        if (! is_array($rawPorts)) {
            return [];
        }

        if (! array_is_list($rawPorts)) {
            $rawPorts = [$rawPorts];
        }

        return array_map(function (array $port): array {
            $types = $this->extractPortTypes($port);

            return [
                'PortName' => $port['Name'] ?? $port['@Name'] ?? null,
                'MaxSize' => isset($port['MaxSize']) ? (float) $port['MaxSize'] : (isset($port['@MaxSize']) ? (float) $port['@MaxSize'] : null),
                'Size' => isset($port['Size']) ? (float) $port['Size'] : (isset($port['@Size']) ? (float) $port['@Size'] : null),
                'Types' => $types,
            ];
        }, $rawPorts);
    }

    /**
     * Extract a flat list of type strings (e.g. "WeaponPersonal.Small") from a raw port definition.
     */
    private function extractPortTypes(array $port): array
    {
        $types = [];
        $rawTypes = Arr::get($port, 'Types.SItemPortDefTypes', []);
        if ($rawTypes === [] || $rawTypes === null) {
            return $types;
        }

        if (! array_is_list($rawTypes)) {
            $rawTypes = [$rawTypes];
        }

        foreach ($rawTypes as $rawType) {
            $major = $rawType['Type'] ?? $rawType['@Type'] ?? $rawType['@type'] ?? null;
            if ($major === null) {
                continue;
            }

            $subTypes = [];
            $rawSubTypes = $rawType['SubTypes'] ?? $rawType['@SubTypes'] ?? $rawType['@subtypes'] ?? null;

            if (is_array($rawSubTypes)) {
                $subTypeEntries = $rawSubTypes['SItemPortDefType'] ?? $rawSubTypes;
                if (! array_is_list($subTypeEntries)) {
                    $subTypeEntries = [$subTypeEntries];
                }

                foreach ($subTypeEntries as $subEntry) {
                    if (is_array($subEntry)) {
                        $value = $subEntry['value'] ?? $subEntry['@value'] ?? null;
                        if (! empty($value)) {
                            $subTypes[] = $value;
                        }
                    } elseif (is_string($subEntry) && $subEntry !== '') {
                        $subTypes[] = $subEntry;
                    }
                }
            } elseif (is_string($rawSubTypes) && $rawSubTypes !== '') {
                $subTypes = array_filter(array_map('trim', explode(',', $rawSubTypes)));
            }

            if ($subTypes === []) {
                $types[] = $major;
            } else {
                foreach ($subTypes as $sub) {
                    $types[] = $major.'.'.$sub;
                }
            }
        }

        return $types;
    }

    /**
     * Detect whether a port accepts personal weapons (any subtype).
     */
    private function isWeaponPersonalPort(array $types): bool
    {
        return array_any($types, fn ($type) => str_starts_with(strtolower((string) $type), 'weaponpersonal'));
    }
}
