<?php

namespace Octfx\ScDataDumper\Services\Vehicle;

use Illuminate\Support\Collection;

/**
 * Analyze weapon systems and turret configurations
 *
 * Calculates weapon fittings, identifies turret types (gimbals, ball turrets, etc.),
 * and analyzes weapon mounting configurations.
 */
final class WeaponSystemAnalyzer implements VehicleDataCalculator
{
    /**
     * Analyze turrets and return weapon fitting data
     *
     * @param  Collection  $turrets  Collection of turret ports
     * @return array Array of weapon fitting data
     */
    public function analyzeTurrets(Collection $turrets): array
    {
        return $turrets->map(fn ($x) => $this->calculateWeaponFitting($x['Port']))->toArray();
    }

    /**
     * Calculate weapon fitting for a port
     *
     * @param  array  $port  Port data
     * @return array Weapon fitting configuration
     */
    public function calculateWeaponFitting(array $port): array
    {
        if ($this->isWeaponMounting($port)) {
            return [
                'Size' => $port['Size'],
                'Gimballed' => $this->isGimbal($port),
                'Turret' => $this->isTurret($port),
                'WeaponSizes' => $this->listTurretPortSizes($port),
            ];
        }

        return [
            'Size' => $port['Size'],
            'Fixed' => true,
            'WeaponSizes' => [$port['Size']],
        ];
    }

    /**
     * Check if port is a weapon mounting (turret or gimbal)
     */
    private function isWeaponMounting(array $port): bool
    {
        return (! empty($port['Uneditable']) || ! $this->acceptsWeapon($port))
            && ($this->isTurret($port) || $this->isGimbal($port));
    }

    /**
     * Check if port has a gimbal mount
     */
    private function isGimbal(array $port): bool
    {
        return isset($port['InstalledItem']['Type']) &&
            $port['InstalledItem']['Type'] === 'Turret.GunTurret';
    }

    /**
     * Check if port has a turret mount
     */
    private function isTurret(array $port): bool
    {
        $types = [
            'Turret.BallTurret',
            'Turret.CanardTurret',
            'Turret.MissileTurret',
            'Turret.NoseMounted',
            'TurretBase.MannedTurret',
            'TurretBase.Unmanned',
        ];

        return isset($port['InstalledItem']['Type']) &&
            in_array($port['InstalledItem']['Type'], $types, true);
    }

    /**
     * Check if port accepts weapon types
     */
    private function acceptsWeapon(array $port): bool
    {
        if (! isset($port['Types']) || ! is_array($port['Types'])) {
            return false;
        }

        $acceptedTypes = ['WeaponGun', 'WeaponGun.Gun', 'WeaponMining.Gun'];
        foreach ($acceptedTypes as $type) {
            if (in_array($type, $port['Types'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * List weapon sizes for turret ports
     */
    private function listTurretPortSizes(array $port): array
    {
        $sizes = [];

        if (! isset($port['InstalledItem']['Ports']) || ! is_array($port['InstalledItem']['Ports'])) {
            return $sizes;
        }

        foreach ($port['InstalledItem']['Ports'] as $subPort) {
            if ($this->acceptsWeapon($subPort)) {
                $sizes[] = $subPort['Size'];
            }
        }

        return $sizes;
    }

    public function canCalculate(VehicleDataContext $context): bool
    {
        return true;
    }

    public function calculate(VehicleDataContext $context): array
    {
        $mannedTurrets = $context->portSummary['mannedTurrets'] ?? collect([]);
        $remoteTurrets = $context->portSummary['remoteTurrets'] ?? collect([]);

        return [
            'MannedTurrets' => $this->analyzeTurrets($mannedTurrets),
            'RemoteTurrets' => $this->analyzeTurrets($remoteTurrets),
        ];
    }

    public function getPriority(): int
    {
        return 40;
    }
}
