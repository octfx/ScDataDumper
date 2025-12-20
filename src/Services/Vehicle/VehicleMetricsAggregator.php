<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use Octfx\ScDataDumper\Helper\Arr;

/**
 * Aggregates emissions and fuel-related metrics by traversing installed items.
 */
final class VehicleMetricsAggregator
{
    public function __construct(
        private readonly EquippedItemWalker $walker,
        private readonly bool $includeRequestedDefaults = true,
    ) {}

    /**
     * @param  array  $loadout  Nested loadout entries from VehicleWrapper
     * @return array{emission: array{ir: float|null, em_min: float|null, em_max: float|null}, fuel_capacity: float|null, quantum_fuel_capacity: float|null, fuel_intake_rate: float|null, fuel_usage: array<string, float>}
     */
    public function aggregate(array $loadout): array
    {
        $irTotal = 0.0;
        $irMaxTotal = 0.0;
        $emMinTotal = 0.0;
        $emMaxTotal = 0.0;
        $fuelCapacity = 0.0;
        $quantumFuelCapacity = 0.0;
        $fuelIntakeRate = 0.0;
        $heatBase = 0.0;
        $heatDraw = 0.0;
        $coolingRate = 0.0;
        $coolerCoolingRate = 0.0;
        $powerDemandMin = 0.0;
        $powerDemandMax = 0.0;
        $shieldHp = 0.0;
        $shieldRegen = 0.0;
        $distortionPool = 0.0;
        $ammoRounds = 0.0;
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

            // IR & EM
            [$irIdle, $irMax] = $this->calculateIr($components);
            [$emMin, $emMax] = $this->calculateEm($components);

            $irTotal += $irIdle;
            $irMaxTotal += $irMax;
            $emMinTotal += $emMin;
            $emMaxTotal += $emMax;
            $heatBase += $this->firstNumeric($components, [
                'EntityComponentHeatConnection.ThermalEnergyBase',
                'EntityComponentHeatConnection.thermalEnergyBase',
            ]) ?? 0.0;
            $heatDraw += $this->firstNumeric($components, [
                'EntityComponentHeatConnection.ThermalEnergyDraw',
                'EntityComponentHeatConnection.thermalEnergyDraw',
            ]) ?? 0.0;
            $coolerCoolingRate += $this->firstNumeric($components, [
                'SCItemCoolerParams.CoolingRate',
            ]) ?? 0.0;
            $powerDemandMin += $emMin / max(1.0, (float) ($this->firstNumeric($components, ['EntityComponentPowerConnection.PowerToEM']) ?? 1.0));
            $powerDemandMax += $emMax / max(1.0, (float) ($this->firstNumeric($components, ['EntityComponentPowerConnection.PowerToEM']) ?? 1.0));

            // Armor signal multipliers (apply once per armor piece; usually only one)
            $attachType = Arr::get($entry['Item'], 'Components.SAttachableComponentParams.AttachDef.Type')
                ?? Arr::get($entry['Item'], 'Type');

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

        $heatMax = $heatBase + $heatDraw;

        // Prefer cooler capacity; fall back to heat connection rates
        $timeToCool = ($coolerCoolingRate > 0 && $heatMax > 0) ? ($heatMax / $coolerCoolingRate) : null;
        $coolingUsagePct = ($coolerCoolingRate > 0 && $heatDraw > 0) ? ($heatDraw / $coolerCoolingRate) * 100 : null;
        $AU_IN_METERS = 149_597_870_700; // 1 AU
        $quantumFuelPerMeter = $quantumFuelRate ? ($quantumFuelRate / 1e6) : 0.0;
        $quantumFuelPerAu = $quantumFuelPerMeter * $AU_IN_METERS;
        $qtTripsPerTankAu = ($quantumFuelPerAu > 0 && $quantumFuelCapacity > 0) ? ($quantumFuelCapacity / $quantumFuelPerAu) : null;

        // Apply armor multipliers (only at the very end)
        $irTotal *= $armorIrMultiplier;
        $irMaxTotal *= $armorIrMultiplier;
        $emMinTotal *= $armorEmMultiplier;
        $emMaxTotal *= $armorEmMultiplier;

        return [
            'emission' => [
                'ir' => $irTotal > 0 ? $irTotal : null,
                'em_min' => $emMinTotal > 0 ? $emMinTotal : null,
                'em_max' => $emMaxTotal > 0 ? $emMaxTotal : null,
            ],
            'fuel_capacity' => $fuelCapacity > 0 ? $fuelCapacity : null,
            'quantum_fuel_capacity' => $quantumFuelCapacity > 0 ? $quantumFuelCapacity : null,
            'fuel_intake_rate' => $fuelIntakeRate > 0 ? $fuelIntakeRate : null,
            'fuel_usage' => $fuelUsage,
            'heat' => [
                'idle' => $heatBase > 0 ? $heatBase : null,
                'max' => $heatMax > 0 ? $heatMax : null,
                'cooling_rate' => $coolerCoolingRate > 0 ? $coolerCoolingRate : null,
                'cooling_usage_pct' => $coolingUsagePct,
                'time_to_cool' => $timeToCool,
            ],
            'power' => [
                'demand_min' => $powerDemandMin > 0 ? $powerDemandMin : null,
                'demand_max' => $powerDemandMax > 0 ? $powerDemandMax : null,
            ],
            'shields' => [
                'hp' => $shieldHp > 0 ? $shieldHp : null,
                'regen' => $shieldRegen > 0 ? $shieldRegen : null,
            ],
            'distortion' => [
                'pool' => $distortionPool > 0 ? $distortionPool : null,
            ],
            'ammo' => [
                //                'rounds' => $ammoRounds > 0 ? $ammoRounds : null,
                'missiles' => $missileCount > 0 ? $missileCount : null,
                'missile_rack_capacity' => $missileRackAmmo > 0 ? $missileRackAmmo : null,
            ],
            'quantum_efficiency' => [
                'fuel_per_au' => $quantumFuelPerAu > 0 ? $quantumFuelPerAu : null,
                'trips_per_tank_au' => $qtTripsPerTankAu,
            ],
            'weapon_storage' => [
                'racks' => $weaponRacks > 0 ? $weaponRacks : null,
                'slots_total' => $weaponSlotsTotal > 0 ? $weaponSlotsTotal : null,
                'slots_rifle' => $weaponSlotsRifle > 0 ? $weaponSlotsRifle : null,
                'slots_pistol' => $weaponSlotsPistol > 0 ? $weaponSlotsPistol : null,
            ],
        ];
    }

    /**
     * @return array{float, float} [irIdle, irMax]
     */
    private function calculateIr(array $components): array
    {
        $tempEnable = Arr::get(
            $components,
            'SEntityPhysicsControllerParams.PhysType.SEntityRigidPhysicsControllerParams.temperature.enable'
        );

        $tempToIr = $this->firstNumeric($components, [
            'EntityComponentHeatConnection.TemperatureToIR',
            'EntityComponentHeatConnection.temperatureToIR',
            'Temperature.SignatureParams.TemperatureToIR',
            'Temperature.signatureParams.temperatureToIR',
            'SEntityPhysicsControllerParams.PhysType.SEntityRigidPhysicsControllerParams.temperature.signatureParams.temperatureToIR',
        ]) ?? 0.0;

        if (! $tempEnable) {
            $tempToIr = 0.0;
        }

        $thermalBase = $this->firstNumeric($components, [
            'EntityComponentHeatConnection.ThermalEnergyBase',
            'EntityComponentHeatConnection.thermalEnergyBase',
        ]) ?? 0.0;

        $thermalDraw = $this->firstNumeric($components, [
            'EntityComponentHeatConnection.ThermalEnergyDraw',
            'EntityComponentHeatConnection.thermalEnergyDraw',
        ]) ?? 0.0;

        $nominalIr = $this->extractNominalSignature($components, 'IR');

        $irIdle = ($thermalBase * $tempToIr) + $nominalIr;
        $irMax = (($thermalBase + $thermalDraw) * $tempToIr) + $nominalIr;

        return [$irIdle, $irMax];
    }

    /**
     * @return array{float, float} [emMin, emMax]
     */
    private function calculateEm(array $components): array
    {
        $powerToEm = (float) ($this->firstNumeric($components, ['EntityComponentPowerConnection.PowerToEM']) ?? 0.0);

        $basePaths = ['EntityComponentPowerConnection.PowerBase'];
        $drawPaths = ['EntityComponentPowerConnection.PowerDraw'];

        if ($this->includeRequestedDefaults) {
            $basePaths[] = 'EntityComponentPowerConnection.PowerBaseRequest';
            $basePaths[] = 'EntityComponentPowerConnection.PowerBaseDefault';
            $drawPaths[] = 'EntityComponentPowerConnection.PowerDrawRequest';
            $drawPaths[] = 'EntityComponentPowerConnection.PowerDrawDefault';
        }

        $powerBase = $this->firstNumeric($components, $basePaths) ?? 0.0;
        $powerDraw = $this->firstNumeric($components, $drawPaths) ?? 0.0;

        $nominalEm = $this->extractNominalSignature($components, 'EM');

        return [
            ($powerBase * $powerToEm), // + $nominalEm,
            ($powerDraw * $powerToEm), // + $nominalEm,
        ];
    }

    private function fuelCapacityFromItem(array $components): float
    {
        $scu = (float) Arr::get($components, 'ResourceContainer.capacity.SStandardCargoUnit.standardCargoUnits', 0);

        return $scu * 1000; // keep existing convention (SCU -> internal units)
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

    /**
     * Extract nominal EM/IR signatures from resource network data.
     */
    private function extractNominalSignature(array $components, string $type): float
    {
        $key = strtoupper($type) === 'EM' ? 'EMSignature' : 'IRSignature';

        $directPaths = [
            "ResourceNetwork.signatureParams.{$key}.nominalSignature",
            "ResourceNetworkSimple.signatureParams.{$key}.nominalSignature",
            'resource.signatureParams.'.strtolower($type).'.nominalSignature',
            'resource.online.signatureParams.'.strtolower($type).'.nominalSignature',
            "ResourceNetworkSimple.online.signatureParams.{$key}.nominalSignature",
            "ResourceNetwork.online.signatureParams.{$key}.nominalSignature",
        ];

        foreach ($directPaths as $path) {
            $value = Arr::get($components, $path);
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        $max = 0.0;

        $stateBuckets = [
            Arr::get($components, 'ResourceNetworkSimple.states', []),
            Arr::get($components, 'ResourceNetwork.states', []),
        ];

        foreach ($stateBuckets as $states) {
            if (! is_array($states)) {
                continue;
            }

            foreach ($states as $state) {
                $value = Arr::get($state, "signatureParams.{$key}.nominalSignature");

                if (is_numeric($value)) {
                    $max = max($max, (float) $value);
                }
            }
        }

        return $max;
    }
}
