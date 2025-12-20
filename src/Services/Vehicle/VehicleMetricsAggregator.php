<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use Octfx\ScDataDumper\Helper\Arr;

/**
 * Aggregates emissions and fuel-related metrics by traversing installed items.
 */
final readonly class VehicleMetricsAggregator
{
    public function __construct(
        private EquippedItemWalker $walker,
    ) {}

    /**
     * @param  array  $loadout  Nested loadout entries from VehicleWrapper
     * @return array{emission: array{ir: float|null, em: float|null, em_with_shields: float|null, em_with_quantum: float|null}, fuel_capacity: float|null, quantum_fuel_capacity: float|null, fuel_intake_rate: float|null, fuel_usage: array<string, float>}
     */
    public function aggregate(array $loadout): array
    {
        // Emission accumulators
        $irTotal = 0.0;
        $emCommon = 0.0;  // components active in both modes
        $emShields = 0.0; // shield generators only
        $emQuantum = 0.0; // quantum drive only

        // Power (segments) & cooling
        $powerUsedSegments = 0.0;
        $powerMaxSegments = 0.0;
        $powerGenerationSegments = 0.0;
        $coolingCapacity = 0.0;          // SCItemCoolerParams.CoolingRate
        $coolerPowerUsed = 0.0;          // Power the coolers currently draw
        $coolerPowerMax = 0.0;           // Max draw inferred via minimumConsumptionFraction

        // Other ship metrics we keep from the previous implementation
        $fuelCapacity = 0.0;
        $quantumFuelCapacity = 0.0;
        $fuelIntakeRate = 0.0;
        $shieldHp = 0.0;
        $shieldRegen = 0.0;
        $distortionPool = 0.0;
        $missileCount = 0.0;
        $missileRackAmmo = 0.0;
        $quantumFuelRate = 0.0;
        $weaponRacks = 0;
        $weaponSlotsTotal = 0;
        $weaponSlotsRifle = 0;
        $weaponSlotsPistol = 0;
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
            $components = $item['Components'] ?? [];
            $attachType = Arr::get($entry['Item'], 'Components.SAttachableComponentParams.AttachDef.Type')
                ?? Arr::get($entry['Item'], 'Type');
            $lowerType = strtolower((string) $attachType);
            $isShield = str_contains($lowerType, 'shield');
            $isQuantum = str_contains($lowerType, 'quantumdrive') || $attachType === 'QuantumDrive';

            // Cooling capacity
            $coolingCapacity += (float) Arr::get($components, 'SCItemCoolerParams.CoolingRate', 0.0);

            // ResourceNetwork
            $resource = $components['ItemResourceComponentParams'] ?? null;
            if ($resource !== null) {
                $state = $this->extractSingleState($resource['states'] ?? null);

                if ($state !== null) {
                    $sig = $state['signatureParams'] ?? [];
                    $emNom = (float) Arr::get($sig, 'EMSignature.nominalSignature', 0.0);
                    $irNom = (float) Arr::get($sig, 'IRSignature.nominalSignature', 0.0);

                    $deltas = $this->normalizeDeltas($state['deltas'] ?? null);

                    $powerBaseSetting = $this->firstNumeric($components, [
                        'EntityComponentPowerConnection.PowerBase',
                        'EntityComponentPowerConnection.PowerBaseDefault',
                        'EntityComponentPowerConnection.PowerBaseRequest',
                    ]);

                    $powerRangeModifier = $this->powerRangeModifier(
                        $powerBaseSetting,
                        $state['rangeParams'] ?? []
                    );

                    foreach ($deltas as $delta) {
                        // Consumption
                        if (isset($delta['ItemResourceDeltaConsumption'])) {
                            $consumption = $delta['ItemResourceDeltaConsumption'];
                            $res = Arr::get($consumption, 'consumption.resource');
                            $rate = $this->extractRate($consumption['consumption']['resourceAmountPerSecond'] ?? []);
                            $minFraction = (float) ($consumption['minimumConsumptionFraction'] ?? 1.0);

                            if ($res === 'Power') {
                                $powerUsedSegments += $rate;
                            }
                        }

                        // Conversion
                        if (isset($delta['ItemResourceDeltaConversion'])) {
                            $conversion = $delta['ItemResourceDeltaConversion'];
                            $consumption = $conversion['consumption'] ?? [];
                            $res = Arr::get($consumption, 'resource');
                            $rate = $this->extractRate($consumption['resourceAmountPerSecond'] ?? []);
                            $minFraction = (float) ($conversion['minimumConsumptionFraction'] ?? 1.0);

                            if ($res === 'Power') {
                                $powerUsedSegments += $rate;

                                if (str_contains(strtolower($entry['portName'] ?? ''), 'cooler')) {
                                    $coolerPowerUsed += $rate;
                                    $coolerPowerMax += $rate / max($minFraction, 1e-6);
                                }
                            }

                            $generation = $conversion['generation'] ?? [];
                            if (($generation['resource'] ?? null) === 'Coolant') {
                            }
                        }

                        // Generation (e.g. power plant)
                        if (isset($delta['ItemResourceDeltaGeneration'])) {
                            $generation = $delta['ItemResourceDeltaGeneration'];
                            if (Arr::get($generation, 'generation.resource') === 'Power') {
                                $powerGenerationSegments += $this->extractRate($generation['generation']['resourceAmountPerSecond'] ?? []);
                            }
                        }
                    }

                    // IR accumulation: nominal IR scaled for coolers by their power ratio (minFraction)
                    if ($irNom > 0) {
                        $irScale = 1.0;
                        $coolerFraction = null;
                        foreach ($deltas as $delta) {
                            if (isset($delta['ItemResourceDeltaConversion'])) {
                                $minFraction = Arr::get($delta['ItemResourceDeltaConversion'], 'minimumConsumptionFraction');
                                if ($minFraction !== null) {
                                    $coolerFraction = (float) $minFraction;
                                    break;
                                }
                            }
                        }
                        if ($coolerFraction !== null && str_contains(strtolower($entry['portName'] ?? ''), 'cooler')) {
                            $irScale = $coolerFraction;
                        }
                        $irTotal += $irNom * $irScale;
                    }

                    $heatSignature = Arr::get($components, 'HeatController.Signature');
                    if (is_array($heatSignature)) {
                        $startIr = (float) Arr::get($heatSignature, 'StartIREmission', 0.0);
                        $irTotal += $startIr;
                    }

                    // EM accumulation: nominal signature scaled by power-range modifier
                    $componentEm = $emNom * $powerRangeModifier;

                    // Bucket component EM based on type
                    if ($isShield) {
                        $emShields += $componentEm;
                    } elseif ($isQuantum) {
                        $emQuantum += $componentEm;
                    } else {
                        $emCommon += $componentEm;
                    }
                }
            }

            // Armor signal multipliers
            if ($attachType === 'Armor') {
                $armorIrMultiplier *= (float) Arr::get($components, 'SCItemVehicleArmorParams.signalInfrared', 1.0);
                $armorEmMultiplier *= (float) Arr::get($components, 'SCItemVehicleArmorParams.signalElectromagnetic', 1.0);
            }

            // Fuel capacities
            if (in_array($attachType, ['FuelTank', 'ExternalFuelTank'], true)) {
                $fuelCapacity += $this->fuelCapacityFromItem($components);
            } elseif ($attachType === 'QuantumFuelTank') {
                $quantumFuelCapacity += $this->fuelCapacityFromItem($components);
            }
            if ($attachType === 'QuantumDrive') {
                $quantumFuelRate = $this->firstNumeric($components, ['SCItemQuantumDriveParams.quantumFuelRequirement']) ?? $quantumFuelRate;
            }

            // Fuel intake
            $fuelIntakeRate += (float) Arr::get($components, 'SCItemFuelIntakeParams.fuelPushRate', 0);

            // Thruster fuel usage grouped by thruster type
            $thrusterType = Arr::get($components, 'SCItemThrusterParams.thrusterType');
            if ($thrusterType !== null) {
                $usage = $this->thrusterFuelUsage($components);
                $bucket = $this->mapThrusterType((string) $thrusterType);

                if ($bucket !== null) {
                    $fuelUsage[$bucket] += $usage;
                }
            }

            // Shields
            $shieldHp += (float) Arr::get($components, 'SCItemShieldGeneratorParams.MaxShieldHealth', 0);
            $shieldRegen += (float) Arr::get($components, 'SCItemShieldGeneratorParams.MaxShieldRegen', 0);

            // Distortion pool
            $distortionPool += (float) Arr::get($components, 'SDistortionParams.Maximum', 0);

            // Ammunition
            // $ammoRounds += (float) Arr::get($components, 'SAmmoContainerComponentParams.maxAmmoCount', 0);

            $type = Arr::get($item, 'Type');
            if (is_string($type) && str_starts_with(strtolower($type), 'missile')) {
                $missileCount++;
            }
            if (is_string($type) && str_starts_with(strtolower($type), 'missilerack')) {
                $missileRackAmmo += (float) Arr::get($components, 'SAmmoContainerComponentParams.maxAmmoCount', 0) ?: Arr::get($components, 'SCItemMissileLauncherParams.maxAmmoCount', 0) ?: 0;
            }

            // Weapon racks / lockers
            $ports = Arr::get($item, 'stdItem.Ports', []);
            $className = strtolower(Arr::get($item, 'ClassName', Arr::get($item, 'className', '')));
            $isWeaponRack = str_contains($className, 'weapon_rack');

            foreach ($ports as $port) {
                $types = $port['Types'] ?? [];
                if (in_array('WeaponPersonal', $types, true)) {
                    $isWeaponRack = true;
                    $maxSize = $port['MaxSize'] ?? $port['Size'] ?? null;
                    $weaponSlotsTotal++;
                    if ($maxSize !== null && $maxSize <= 2) {
                        $weaponSlotsPistol++;
                    } else {
                        $weaponSlotsRifle++;
                    }
                }
            }

            if ($isWeaponRack) {
                $weaponRacks++;
            }
        }

        // Cooling load
        $coolingUsagePct = ($coolerPowerMax > 0)
            ? ($coolerPowerUsed / $coolerPowerMax) * 100
            : null;

        $irTotal *= $armorIrMultiplier;
        $emCommon *= $armorEmMultiplier;
        $emShields *= $armorEmMultiplier;
        $emQuantum *= $armorEmMultiplier;

        $emWithShields = $emCommon + $emShields;
        $emWithQuantum = $emCommon + $emQuantum;

        return [
            'emission' => [
                'ir' => $irTotal > 0 ? $irTotal : null,
                'em' => $emWithShields > 0 ? $emWithShields : null,
                'em_with_shields' => $emWithShields > 0 ? $emWithShields : null,
                'em_with_quantum' => $emWithQuantum > 0 ? $emWithQuantum : null,
            ],
            'fuel_capacity' => $fuelCapacity > 0 ? $fuelCapacity : null,
            'quantum_fuel_capacity' => $quantumFuelCapacity > 0 ? $quantumFuelCapacity : null,
            'fuel_intake_rate' => $fuelIntakeRate > 0 ? $fuelIntakeRate : null,
            'fuel_usage' => $fuelUsage,
            'heat' => [
                'base_generation' => $coolerPowerUsed,
                'cooling_rate' => $coolingCapacity > 0 ? $coolingCapacity : null,
                'cooling_usage_pct' => $coolingUsagePct,
            ],
            'power' => [
                'used_segments' => $powerUsedSegments > 0 ? $powerUsedSegments : null,
                // 'max_segments' => $powerMaxSegments > 0 ? $powerMaxSegments : null,
                'generation_segments' => $powerGenerationSegments > 0 ? $powerGenerationSegments : null,
            ],
            'shields' => [
                'hp' => $shieldHp > 0 ? $shieldHp : null,
                // TODO: Factor?
                'regen' => $shieldRegen > 0 ? $shieldRegen * 0.66 : null,
            ],
            'distortion' => [
                'pool' => $distortionPool > 0 ? $distortionPool : null,
            ],
            'ammo' => [
                // 'rounds' => $ammoRounds > 0 ? $ammoRounds : null,
                'missiles' => $missileCount > 0 ? $missileCount : null,
                'missile_rack_capacity' => $missileRackAmmo > 0 ? $missileRackAmmo : null,
            ],
            'weapon_storage' => [
                'racks' => $weaponRacks > 0 ? $weaponRacks : null,
                'slots_total' => $weaponSlotsTotal > 0 ? $weaponSlotsTotal : null,
                'slots_rifle' => $weaponSlotsRifle > 0 ? $weaponSlotsRifle : null,
                'slots_pistol' => $weaponSlotsPistol > 0 ? $weaponSlotsPistol : null,
            ],
        ];
    }

    private function fuelCapacityFromItem(array $components): float
    {
        $scu = (float) Arr::get($components, 'ResourceContainer.capacity.SStandardCargoUnit.standardCargoUnits', 0);

        return $scu * 1000;
    }

    private function thrusterFuelUsage(array $components): float
    {
        $burnPer10k = (float) Arr::get($components, 'SCItemThrusterParams.fuelBurnRatePer10KNewton', 0);
        $thrust = (float) Arr::get($components, 'SCItemThrusterParams.thrustCapacity', 0);

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

    private function extractSingleState(mixed $states): ?array
    {
        if (is_array($states) && array_key_exists('ItemResourceState', $states)) {
            return $states['ItemResourceState'];
        }

        $outState = null;
        foreach ($states as $state) {
            if (($state['name'] ?? '') === 'Online') {
                $outState = $state;
            }

            if (($state['name'] ?? '') === 'Travelling') {
                return $state;
            }
        }

        return $outState;
    }

    /**
     * Ensure deltas are always an array of associative arrays.
     */
    private function normalizeDeltas(mixed $deltas): array
    {
        if ($deltas === null) {
            return [];
        }

        if (is_array($deltas) && array_is_list($deltas)) {
            return $deltas;
        }

        return [is_array($deltas) ? $deltas : []];
    }

    /**
     * Extract a numeric rate from resourceAmountPerSecond block.
     */
    private function extractRate(array $resourceAmountPerSecond): float
    {
        if ($resourceAmountPerSecond === []) {
            return 0.0;
        }

        $first = reset($resourceAmountPerSecond);
        if (! is_array($first)) {
            return 0.0;
        }

        $value = reset($first);

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function powerRangeModifier(?float $value, array $ranges): float
    {
        $registerRanges = array_values(array_filter(
            $ranges,
            static fn ($range) => isset($range['RegisterRange'])
        ));

        usort($registerRanges, static fn ($a, $b) => ($a['Start'] ?? 0) <=> ($b['Start'] ?? 0));

        if ($registerRanges === [] || $value === null) {
            return 1.0;
        }

        foreach ($registerRanges as $idx => $range) {
            $start = $range['Start'] ?? 0.0;
            $modifier = (float) ($range['Modifier'] ?? 1.0);
            $nextStart = $registerRanges[$idx + 1]['Start'] ?? null;

            if ($value >= $start && ($nextStart === null || $value < $nextStart)) {
                return $modifier;
            }
        }

        return 1.0;
    }
}
