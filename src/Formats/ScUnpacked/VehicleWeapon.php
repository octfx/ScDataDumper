<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Illuminate\Support\Arr;
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
        $coolingPerSecond = (float) ($heatParams?->get('@coolingPerSecond') ?? 0.0);
        $coolDelay = (float) ($heatParams?->get('@timeTillCoolingStarts') ?? 0.0);
        $overheatTemp = (float) ($heatParams?->get('@overheatTemperature') ?? 0.0);
        $minTemp = (float) ($heatParams?->get('@minTemperature') ?? 0.0);
        $fixTime = (float) ($heatParams?->get('@overheatFixTime') ?? 0.0);

        $damagePerShot = (float) ($mode['DamagePerShot'] ?? 0.0);

        $shotsPerSec = $rpm / 60.0;
        $heatPerSecond = $heatPerShot * $shotsPerSec;

        $temperatureBudget = max(0.0, $overheatTemp - $minTemp);
        $shotsToOverheatExact = ($heatPerShot > 0 && $temperatureBudget > 0) ? ($temperatureBudget / $heatPerShot) : null;
        $shotsToOverheat = $shotsToOverheatExact !== null ? floor($shotsToOverheatExact) : null;
        $timeToOverheat = ($shotsToOverheat !== null && $shotsPerSec > 0) ? $shotsToOverheat / $shotsPerSec : null;

        // sustained DPS over 60s window
        $window = 60.0;
        $sustainedDamage = null;
        $sustainedDps = null;

        if ($shotsPerSec > 0 && $damagePerShot > 0) {
            $maxAmmoLoad = (float) ($regenParams?->get('@maxAmmoLoad') ?? 0.0);
            $maxRegenPerSec = (float) ($regenParams?->get('@maxRegenPerSec') ?? 0.0);
            $regenCooldown = (float) ($regenParams?->get('@regenerationCooldown') ?? 0.0);

            if ($maxAmmoLoad > 0 && $maxRegenPerSec > 0) {
                $cycleFireTime = $maxAmmoLoad / $shotsPerSec;
                $cycleRegenTime = $maxAmmoLoad / $maxRegenPerSec;
                $cycleTotalTime = $cycleFireTime + $regenCooldown + $cycleRegenTime;

                if ($cycleTotalTime > 0) {
                    $cycleDamage = $maxAmmoLoad * $damagePerShot;
                    $cycles = floor($window / $cycleTotalTime);
                    $remTime = $window - ($cycles * $cycleTotalTime);
                    $remShots = min($maxAmmoLoad, $shotsPerSec * max(0.0, $remTime));
                    $remDamage = $remShots * $damagePerShot;
                    $sustainedDamage = ($cycles * $cycleDamage) + $remDamage;
                    $sustainedDps = $window > 0 ? $sustainedDamage / $window : null;
                }
            } elseif ($shotsToOverheatExact === null) {
                $sustainedDamage = $damagePerShot * $shotsPerSec * $window;
                $sustainedDps = $damagePerShot * $shotsPerSec;
            } else {
                $cycleFireTime = $shotsToOverheatExact / $shotsPerSec;
                $cycleDamage = $damagePerShot * $shotsToOverheatExact;
                $cycleTotalTime = $cycleFireTime + $fixTime;

                $cycles = ($cycleTotalTime > 0) ? floor($window / $cycleTotalTime) : 0;
                $remTime = $window - ($cycles * $cycleTotalTime);
                $remShots = min($shotsPerSec * max(0.0, $remTime), $shotsToOverheatExact);
                $remDamage = $remShots * $damagePerShot;
                $sustainedDamage = ($cycles * $cycleDamage) + $remDamage;
                $sustainedDps = $window > 0 ? $sustainedDamage / $window : null;
            }
        }

        $maximumDamage = $mode['MaxDamagePerMagazine'] ?? null;
        $isInfiniteMaximum = ($maximumDamage === null || $maximumDamage <= 0 || $regenParams instanceof Element);

        $actionSequence = $this->get()?->get('fireActions/SWeaponActionSequenceParams');

        $vehicleStats = [
            'FireMode' => Arr::get($mode, 'Name'),
            'Spread' => $actionSequence ? $this->extractSpread($actionSequence) : null,
            'Damage' => [
                'Sustained60s' => $this->roundStat($sustainedDps, 1),
                'Burst' => $mode['Dps'] ?? $mode['DamagePerSecond'] ?? null,
                'AlphaTotal' => $mode['Alpha'] ?? $mode['DamagePerShot'] ?? null,
                'DpsTotal' => $mode['Dps'] ?? null,
                'Maximum' => $isInfiniteMaximum ? 'Infinite' : $maximumDamage,
                'Alpha' => [
                    'Physical' => Arr::get($mode, 'AlphaPhysical'),
                    'Energy' => Arr::get($mode, 'AlphaEnergy'),
                    'Distortion' => Arr::get($mode, 'AlphaDistortion'),
                    'Thermal' => Arr::get($mode, 'AlphaThermal'),
                    'Biochemical' => Arr::get($mode, 'AlphaBiochemical'),
                    'Stun' => Arr::get($mode, 'AlphaStun'),
                ],
                'Dps' => [
                    'Physical' => Arr::get($mode, 'DpsPhysical'),
                    'Energy' => Arr::get($mode, 'DpsEnergy'),
                    'Distortion' => Arr::get($mode, 'DpsDistortion'),
                    'Thermal' => Arr::get($mode, 'DpsThermal'),
                    'Biochemical' => Arr::get($mode, 'DpsBiochemical'),
                    'Stun' => Arr::get($mode, 'DpsStun'),
                ],
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
                'RequestedAmmoLoad' => $regenParams->get('@requestedAmmoLoad'),
                'CostPerBullet' => $regenParams->get('@regenerationCostPerBullet'),
                'Cooldown' => $regenParams->get('@regenerationCooldown'),
                'MaxRegenPerSec' => $regenParams->get('@maxRegenPerSec'),
                'MaxAmmoLoad' => $regenParams->get('@maxAmmoLoad'),
            ];
        }

        return $this->removeNullValues($vehicleStats);
    }
}
