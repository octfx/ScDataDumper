<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle;

use Illuminate\Support\Arr;

/**
 * Aggregates weapon DPS/alpha, missile damage, and countermeasure counts
 *
 * Walks standardised parts to collect weaponry data:
 * - Fixed weapons: DPS/Alpha/Sustained totals
 * - Turret weapons: grouped by turret hardpoint with per-turret totals
 * - Missiles: damage per type across all installed missiles
 * - Countermeasures: counts by type (Flare/Noise/Chaff)
 */
final readonly class WeaponDpsAggregator implements VehicleDataCalculator
{
    private const array DAMAGE_TYPES = ['Physical', 'Energy', 'Distortion', 'Thermal', 'Biochemical', 'Stun'];

    public function __construct(
        private StandardisedPartWalker $walker,
        private ItemTypeResolver $itemTypeResolver = new ItemTypeResolver,
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

        $fixedWeapons = [];
        $turretMap = []; // hardpoint name => [weaponData, ...]
        $missileDamage = [];
        $missileCount = 0;
        $countermeasures = [];

        $currentTurretHardpoint = null;
        $currentTurretDepth = -1;

        foreach ($this->walker->walkItems($loadout) as $entry) {
            $item = $entry['Item'];

            if ($item === null) {
                continue;
            }

            $itemType = $this->itemTypeResolver->resolveSemanticType($item);
            $stdItem = Arr::get($item, 'stdItem');

            if ($stdItem === null) {
                continue;
            }

            $depth = count($entry['path'] ?? []);

            if ($this->isTurretBaseType($itemType, $entry['Port'])) {
                $currentTurretHardpoint = $entry['portName'];
                $currentTurretDepth = $depth;

                continue;
            }

            if ($currentTurretHardpoint !== null && $depth <= $currentTurretDepth) {
                $currentTurretHardpoint = null;
                $currentTurretDepth = -1;
            }

            if ($this->isWeaponType($itemType)) {
                $weaponData = $this->extractWeaponData($stdItem);
                if ($weaponData !== null) {
                    if ($currentTurretHardpoint !== null) {
                        $turretMap[$currentTurretHardpoint][] = $weaponData;
                    } else {
                        $fixedWeapons[] = $weaponData;
                    }
                }
            }

            if ($this->isMissileType($itemType)) {
                $missileCount++;
                $this->accumulateMissileDamage($stdItem, $missileDamage);
            }

            if ($this->isCountermeasureType($itemType)) {
                $this->accumulateCountermeasure($stdItem, $countermeasures);
            }
        }

        $result = [];

        $fixedSummary = $this->buildFixedWeaponSummary($fixedWeapons);
        if ($fixedSummary !== null) {
            $result['FixedWeapons'] = $fixedSummary;
        }

        $turretSummaries = $this->buildTurretSummaries($turretMap, $context->turretControlMap);
        if ($turretSummaries !== []) {
            $result['Turrets'] = $turretSummaries;
        }

        $pilotDps = (float) ($fixedSummary['DpsTotal'] ?? 0.0);
        $pilotAlpha = (float) ($fixedSummary['AlphaTotal'] ?? 0.0);
        $pilotSustained = (float) ($fixedSummary['SustainedDpsTotal'] ?? 0.0);

        // Split turret DPS: bridge-controllable turrets add to pilot totals
        $bridgeControllableSet = array_flip($context->turretControlMap);
        $turretDps = 0.0;
        $turretAlpha = 0.0;
        $turretSustained = 0.0;

        foreach ($turretSummaries as $turret) {
            $hp = $turret['HardpointName'];
            $isBridgeControllable = isset($bridgeControllableSet[$hp]);

            if ($isBridgeControllable) {
                $pilotDps += (float) ($turret['DpsTotal'] ?? 0.0);
                $pilotAlpha += (float) ($turret['AlphaTotal'] ?? 0.0);
                if (isset($turret['SustainedDpsTotal'])) {
                    $pilotSustained += (float) $turret['SustainedDpsTotal'];
                }
            } else {
                $turretDps += (float) ($turret['DpsTotal'] ?? 0.0);
                $turretAlpha += (float) ($turret['AlphaTotal'] ?? 0.0);
                if (isset($turret['SustainedDpsTotal'])) {
                    $turretSustained += (float) $turret['SustainedDpsTotal'];
                }
            }
        }

        if ($pilotDps > 0) {
            $result['PilotDps'] = round($pilotDps, 1);
        }

        if ($pilotAlpha > 0) {
            $result['PilotAlpha'] = round($pilotAlpha, 1);
        }

        if ($pilotSustained > 0) {
            $result['PilotSustainedDps'] = round($pilotSustained, 1);
        }

        if ($turretDps > 0) {
            $result['TurretDps'] = round($turretDps, 1);
        }

        if ($turretAlpha > 0) {
            $result['TurretAlpha'] = round($turretAlpha, 1);
        }

        if ($turretSustained > 0) {
            $result['TurretSustainedDps'] = round($turretSustained, 1);
        }

        if ($missileCount > 0) {
            $totalMissileDamage = array_sum($missileDamage);
            $result['Missiles'] = [
                'Count' => $missileCount,
                'Damage' => array_merge($missileDamage, ['Total' => round($totalMissileDamage, 1)]),
            ];
            $result['TotalMissiles'] = round($totalMissileDamage, 1);
        }

        if ($countermeasures !== []) {
            $result['Countermeasures'] = $countermeasures;
        }

        if ($result === []) {
            return [];
        }

        return ['Weaponry' => $result];
    }

    public function getPriority(): int
    {
        return 40;
    }

    private function isWeaponType(?string $type): bool
    {
        return $type !== null && str_starts_with($type, 'WeaponGun');
    }

    private function isTurretBaseType(?string $type, ?array $port = null): bool
    {
        if ($type === null) {
            return false;
        }

        // Explicit turret base types (TurretBase.MannedTurret, TurretBase.RemoteTurret)
        if (str_starts_with($type, 'TurretBase')) {
            return true;
        }

        // Port category indicates a turret (set by PortClassifierService)
        $category = $port['Category'] ?? null;
        if ($category !== null) {
            return in_array($category, ['Remote turrets', 'Manned turrets', 'PDC turrets', 'Autonomous turrets'], true);
        }

        return false;
    }

    private function isMissileType(?string $type): bool
    {
        if ($type === null) {
            return false;
        }

        return str_starts_with($type, 'Missile.')
            || strcasecmp($type, 'Missile') === 0
            || str_starts_with($type, 'Bomb.')
            || strcasecmp($type, 'Bomb') === 0;
    }

    private function isCountermeasureType(?string $type): bool
    {
        return $type !== null && strcasecmp($type, 'WeaponDefensive') === 0;
    }

    /**
     * Extract weapon damage data from stdItem.Weapon.Damage.
     *
     * @return array{ClassName: string|null, Dps: float, SustainedDps: float|null, Alpha: float}|null
     */
    private function extractWeaponData(array $stdItem): ?array
    {
        $damage = Arr::get($stdItem, 'Weapon.Damage');
        if ($damage === null) {
            return null;
        }

        $dps = (float) ($damage['DpsTotal'] ?? $damage['Burst'] ?? 0.0);
        $alpha = (float) ($damage['AlphaTotal'] ?? 0.0);
        $sustained = isset($damage['Sustained']) && is_numeric($damage['Sustained'])
            ? (float) $damage['Sustained']
            : null;

        if ($dps <= 0 && $alpha <= 0) {
            return null;
        }

        return [
            'UUID' => Arr::get($stdItem, 'UUID'),
            'ClassName' => Arr::get($stdItem, 'ClassName'),
            'Name' => Arr::get($stdItem, 'Name'),
            'Dps' => round($dps, 1),
            'SustainedDps' => $sustained !== null ? round($sustained, 1) : null,
            'Alpha' => round($alpha, 1),
        ];
    }

    /**
     * Accumulate missile/bomb damage by type from stdItem.Missile.Damage or stdItem.Bomb.Damage.
     */
    private function accumulateMissileDamage(array $stdItem, array &$missileDamage): void
    {
        $damage = Arr::get($stdItem, 'Missile.Damage') ?? Arr::get($stdItem, 'Bomb.Damage');
        if ($damage === null || ! is_array($damage)) {
            return;
        }

        foreach (self::DAMAGE_TYPES as $type) {
            $value = (float) ($damage[$type] ?? 0.0);
            if (! isset($missileDamage[$type])) {
                $missileDamage[$type] = 0.0;
            }
            $missileDamage[$type] += $value;
        }
    }

    /**
     * Accumulate countermeasures by type from stdItem.WeaponDefensive.
     */
    private function accumulateCountermeasure(array $stdItem, array &$countermeasures): void
    {
        $type = Arr::get($stdItem, 'WeaponDefensive.Type');
        $capacity = Arr::get($stdItem, 'WeaponDefensive.Capacity');

        if ($type === null || $capacity === null) {
            return;
        }

        $type = ucfirst(strtolower((string) $type));
        $capacity = (int) $capacity;

        if (! isset($countermeasures[$type])) {
            $countermeasures[$type] = 0;
        }
        $countermeasures[$type] += $capacity;
    }

    /**
     * Build fixed weapon summary with totals.
     */
    private function buildFixedWeaponSummary(array $weapons): ?array
    {
        if ($weapons === []) {
            return null;
        }

        $dpsTotal = 0.0;
        $alphaTotal = 0.0;
        $sustainedTotal = 0.0;
        $hasSustained = false;

        foreach ($weapons as $w) {
            $dpsTotal += $w['Dps'];
            $alphaTotal += $w['Alpha'];
            if ($w['SustainedDps'] !== null) {
                $sustainedTotal += $w['SustainedDps'];
                $hasSustained = true;
            }
        }

        return [
            'DpsTotal' => round($dpsTotal, 1),
            'SustainedDpsTotal' => $hasSustained ? round($sustainedTotal, 1) : null,
            'AlphaTotal' => round($alphaTotal, 1),
            'Weapons' => $weapons,
        ];
    }

    /**
     * Build per-turret summaries.
     *
     * @param  array<string, array>  $turretMap
     * @param  array<string>  $turretControlMap  Bridge-controllable turret hardpoint names
     */
    private function buildTurretSummaries(array $turretMap, array $turretControlMap = []): array
    {
        $result = [];
        $bridgeSet = array_flip($turretControlMap);

        foreach ($turretMap as $hardpoint => $weapons) {
            $dpsTotal = 0.0;
            $alphaTotal = 0.0;
            $sustainedTotal = 0.0;
            $hasSustained = false;
            $isPilotSlaveable = isset($bridgeSet[$hardpoint]);

            $stampedWeapons = array_map(
                static fn (array $w): array => [...$w, 'IsPilotSlaveable' => $isPilotSlaveable],
                $weapons,
            );

            foreach ($weapons as $w) {
                $dpsTotal += $w['Dps'];
                $alphaTotal += $w['Alpha'];
                if ($w['SustainedDps'] !== null) {
                    $sustainedTotal += $w['SustainedDps'];
                    $hasSustained = true;
                }
            }

            $result[] = [
                'HardpointName' => $hardpoint,
                'DpsTotal' => round($dpsTotal, 1),
                'SustainedDpsTotal' => $hasSustained ? round($sustainedTotal, 1) : null,
                'AlphaTotal' => round($alphaTotal, 1),
                'IsPilotSlaveable' => $isPilotSlaveable,
                'Weapons' => $stampedWeapons,
            ];
        }

        return $result;
    }
}
