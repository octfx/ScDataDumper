<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;

/**
 * Vehicle-mounted weapons add sustained / overheat / capacitor stats on top of the base output.
 */
final class VehicleWeapon extends AbstractWeapon
{
    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $base = $this->buildBaseWeaponArray();

        $vehicleStats = $this->buildVehicleStats($base);

        return $this->removeNullValues(array_merge($base, $vehicleStats));
    }

    private function buildVehicleStats(array $base): array
    {
        $mode = $base['Modes'][0] ?? null; // assume primary fire mode for sustained calc
        if (! is_array($mode) || empty($mode['RoundsPerMinute'])) {
            return [];
        }

        $heatParams = $this->item->get('Components/SCItemWeaponComponentParams/connectionParams/simplifiedHeatParams/SWeaponSimplifiedHeatParams');
        $regenParams = $this->item->get('Components/SCItemWeaponComponentParams/weaponRegenConsumerParams/SWeaponRegenConsumerParams');

        $rpm = (float) $mode['RoundsPerMinute'];
        $heatPerShot = (float) ($mode['HeatPerShot'] ?? 0.0);
        $coolingPerSecond = (float) ($heatParams?->get('coolingPerSecond') ?? 0.0);
        $coolDelay = (float) ($heatParams?->get('timeTillCoolingStarts') ?? 0.0);
        $overheatTemp = (float) ($heatParams?->get('overheatTemperature') ?? 0.0);
        $minTemp = (float) ($heatParams?->get('minTemperature') ?? 0.0);
        $fixTime = (float) ($heatParams?->get('overheatFixTime') ?? 0.0);

        $damagePerShot = (float) ($mode['DamagePerShot'] ?? 0.0);

        $shotsPerSec = $rpm / 60.0;
        $heatPerSecond = $heatPerShot * $shotsPerSec;

        $temperatureBudget = max(0.0, $overheatTemp - $minTemp);
        $shotsToOverheat = ($heatPerShot > 0) ? floor($temperatureBudget / $heatPerShot) : null;
        $timeToOverheat = ($shotsToOverheat !== null && $shotsPerSec > 0) ? $shotsToOverheat / $shotsPerSec : null;

        // sustained DPS over 60s window
        $window = 60.0;
        if ($shotsToOverheat === null) {
            $sustainedDamage = $damagePerShot * $shotsPerSec * $window;
            $sustainedDps = $damagePerShot * $shotsPerSec;
            $cycleDamage = null;
            $cycleTotalTime = null;
        } else {
            $cycleFireTime = $timeToOverheat ?? 0.0;
            $cycleDamage = $damagePerShot * ($shotsToOverheat ?? 0.0);
            $cycleTotalTime = $cycleFireTime + $fixTime;

            $cycles = ($cycleTotalTime > 0) ? floor($window / $cycleTotalTime) : 0;
            $remTime = $window - ($cycles * $cycleTotalTime);
            $remShots = min($shotsPerSec * max(0.0, $remTime), $shotsToOverheat ?? 0.0);
            $remDamage = $remShots * $damagePerShot;
            $sustainedDamage = ($cycles * $cycleDamage) + $remDamage;
            $sustainedDps = $window > 0 ? $sustainedDamage / $window : null;
        }

        $vehicleStats = [
            'Spread' => [
                'Min' => $this->get()?->get('/fireActions/SWeaponActionFireChargedParams/weaponAction/SWeaponActionFireSingleParams/launchParams/SProjectileLauncher/spreadParams@min'),
                'Max' => $this->get()?->get('/fireActions/SWeaponActionFireChargedParams/weaponAction/SWeaponActionFireSingleParams/launchParams/SProjectileLauncher/spreadParams@max'),
            ],
            'Sustained' => [
                'Damage60s' => $this->roundStat($sustainedDamage, 1),
                'Dps60s' => $this->roundStat($sustainedDps, 1),
                'CycleDamage' => $this->roundStat($cycleDamage, 1),
                'CycleTime' => $this->roundStat($cycleTotalTime, 2),
            ],
        ];

        if ($heatParams instanceof Element) {
            $vehicleStats['Heat'] = [
                'HeatPerShot' => $this->roundStat($heatPerShot, 2),
                'CoolingDelay' => $this->roundStat($coolDelay, 3),
                'CoolingPerSecond' => $this->roundStat($coolingPerSecond, 1),
                'OverheatTemperature' => $overheatTemp,
                'OverheatFixTime' => $this->roundStat($fixTime, 2),
                'ShotsToOverheat' => $shotsToOverheat,
                'TimeToOverheat' => $this->roundStat($timeToOverheat, 2),
            ];
        }

        if ($regenParams instanceof Element) {
            $vehicleStats['Capacitor'] = [
                'RequestedAmmoLoad' => $regenParams->get('requestedAmmoLoad'),
                'CostPerBullet' => $regenParams->get('regenerationCostPerBullet'),
                'Cooldown' => $regenParams->get('regenerationCooldown'),
                'MaxRegenPerSec' => $regenParams->get('maxRegenPerSec'),
                'MaxAmmoLoad' => $regenParams->get('maxAmmoLoad'),
            ];
        }

        return $this->removeNullValues($vehicleStats);
    }
}
