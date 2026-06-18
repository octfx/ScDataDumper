<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

/**
 * Classifies ports from a RecursiveLoadoutPortIndex into semantic system buckets.
 *
 * Each port is assigned to exactly one system key (see VehicleSystemKeys) by
 * normalized Type/SubType, ClassName heuristics, and structural context.
 * Unmatched ports are skipped; SystemsBuilder emits all keys with empty buckets.
 */
final class SystemPortClassifier
{
    /**
     * Thruster types recognized by the classifier.
     *
     * @var list<string>
     */
    private const array THRUSTER_TYPES = [
        'MainThruster',
        'RetroThruster',
        'VtolThruster',
        'VTOLThruster',
        'ManneuverThruster',
        'ManeuverThruster',
    ];

    /**
     * Classify all ports from the index into semantic system buckets.
     *
     * @return array<string, list<array<string, mixed>>> SystemKey => [port references]
     */
    public function classify(RecursiveLoadoutPortIndex $index): array
    {
        /** @var array<string, list<array<string, mixed>>> $buckets */
        $buckets = [];

        foreach ($index->all() as $port) {
            $ref = $index->getReferenceObject($port['PortId']);
            if ($ref === null) {
                continue;
            }

            $systemKey = $this->classifyPort($ref, $index);
            if ($systemKey === null) {
                continue;
            }

            $buckets[$systemKey][] = $ref;
        }

        return $buckets;
    }

    /**
     * @param  array<string, mixed>  $ref
     * @param  RecursiveLoadoutPortIndex  $index  For parent lookups
     */
    private function classifyPort(array $ref, RecursiveLoadoutPortIndex $index): ?string
    {
        $type = $ref['Type'] ?? null;
        $subType = $ref['SubType'] ?? null;
        $className = $ref['ClassName'] ?? null;

        return match ($type) {
            'Shield' => 'Shields',
            'QuantumDrive' => 'QuantumDrives',
            'JumpDrive' => 'JumpDrives',
            'FlightController' => 'FlightControllers',
            'QuantumFuelTank' => 'QuantumFuelTanks',
            'FuelTank' => 'HydrogenFuelTanks',
            'FuelIntake' => 'FuelIntakes',
            'Cooler' => 'Coolers',
            'PowerPlant' => 'PowerPlants',
            'Armor' => 'Armors',
            'Radar' => 'Radars',
            'LifeSupportGenerator' => 'LifeSupport',
            'WeaponGun' => 'Weapons',
            'Missile' => 'Missiles',
            'MissileLauncher' => 'MissileRacks',

            default => $this->classifyByComplexRules($type, $subType, $className, $ref, $index),
        };
    }

    /**
     * Classification rules beyond simple Type matching (thrusters, turrets, mounts, etc.).
     *
     * @param  array<string, mixed>  $ref
     */
    private function classifyByComplexRules(
        ?string $type,
        ?string $subType,
        ?string $className,
        array $ref,
        RecursiveLoadoutPortIndex $index,
    ): ?string {
        if ($type !== null && in_array($type, self::THRUSTER_TYPES, true)) {
            return 'Thrusters';
        }

        if ($type === 'TurretBase') {
            return match ($subType) {
                'MannedTurret' => 'MannedTurrets',
                'RemoteTurret' => 'RemoteTurrets',
                'PdcTurret' => 'PdcTurrets',
                default => null,
            };
        }

        if ($this->isWeaponMount($type, $subType, $className, $ref, $index)) {
            return 'WeaponMounts';
        }

        if ($type === 'WeaponDefensive' || $subType === 'CounterMeasure') {
            return 'CounterMeasures';
        }

        if ($type === 'Paint' || $subType === 'Paint') {
            return 'Paints';
        }

        if ($type === 'Mining' || str_contains((string) $type, 'Mining')) {
            return 'Mining';
        }

        if ($type === 'Salvage' || str_contains((string) $type, 'Salvage')) {
            return 'Salvage';
        }

        if ($type === 'TractorBeam' || str_contains((string) $type, 'Tractor')) {
            return 'TractorBeams';
        }

        if ($type === 'EMP' || stripos((string) $type, 'emp') === 0) {
            return 'Emps';
        }

        if ($type === 'QED'
            || $type === 'QuantumInterdictionGenerator'
            || stripos((string) $type, 'qed') === 0
            || stripos((string) $type, 'qig') === 0
        ) {
            return 'Qeds';
        }

        if ($type === 'CargoGrid' || (str_contains((string) $type, 'Cargo') && str_contains((string) $type, 'Grid'))) {
            return 'CargoGrids';
        }

        if (str_contains((string) $type, 'WeaponLocker') || str_contains((string) $type, 'WeaponRack')) {
            return 'WeaponLockers';
        }

        if ($type === 'AI' || $type === 'Ai' || str_contains((string) $type, 'AiModule') || str_contains((string) $type, 'AiBlade')) {
            return 'AiModules';
        }

        if ($type === 'Module' || str_contains((string) $type, 'Module')) {
            if (! str_contains((string) $type, 'Ai')) {
                return 'Modules';
            }
        }

        if (str_contains((string) $type, 'Dock') || str_contains((string) $subType ?? '', 'Dock')) {
            return 'DockedVehicles';
        }

        return null;
    }

    /**
     * Determine if a port is a weapon mount (gimbal/adapter): turret child of a
     * TurretBase, a Mount_/Gimbal/VariPuck class name, or a Type containing 'Mount'.
     *
     * @param  array<string, mixed>  $ref
     */
    private function isWeaponMount(
        ?string $type,
        ?string $subType,
        ?string $className,
        array $ref,
        RecursiveLoadoutPortIndex $index,
    ): bool {
        if ($type === 'Turret' && $subType === 'GunTurret') {
            $parentPortId = $ref['ParentPortId'] ?? null;

            if ($parentPortId !== null) {
                $parent = $index->findByPortId($parentPortId);

                if ($parent !== null) {
                    $parentRawType = $parent['Type'] ?? null;

                    if ($parentRawType !== null && str_starts_with($parentRawType, 'TurretBase.')) {
                        return true;
                    }
                }
            }
        }

        if ($className !== null) {
            if (str_starts_with($className, 'Mount_') || str_contains($className, 'Gimbal') || str_contains($className, 'VariPuck')) {
                return true;
            }
        }

        return $type !== null && str_contains($type, 'Mount')
            && ! in_array($type, ['ShieldMount', 'CoolerMount', 'PowerPlantMount'], true);
    }
}
