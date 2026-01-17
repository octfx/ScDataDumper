<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Illuminate\Support\Arr;

/**
 * Personal / FPS weapons
 */
final class PersonalWeapon extends AbstractWeapon
{
    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $base = $this->buildBaseWeaponArray();
        $mode = $base['Modes'][0] ?? null; // assume primary fire mode for summary

        if (! is_array($mode)) {
            return $base;
        }

        $alpha = $this->removeNullValues([
            'Physical' => Arr::get($mode, 'AlphaPhysical'),
            'Energy' => Arr::get($mode, 'AlphaEnergy'),
            'Distortion' => Arr::get($mode, 'AlphaDistortion'),
            'Thermal' => Arr::get($mode, 'AlphaThermal'),
            'Biochemical' => Arr::get($mode, 'AlphaBiochemical'),
            'Stun' => Arr::get($mode, 'AlphaStun'),
        ]);

        $dps = $this->removeNullValues([
            'Physical' => Arr::get($mode, 'DpsPhysical'),
            'Energy' => Arr::get($mode, 'DpsEnergy'),
            'Distortion' => Arr::get($mode, 'DpsDistortion'),
            'Thermal' => Arr::get($mode, 'DpsThermal'),
            'Biochemical' => Arr::get($mode, 'DpsBiochemical'),
            'Stun' => Arr::get($mode, 'DpsStun'),
        ]);

        $capacity = $base['Capacity'] ?? null;
        $maxPerMag = ($capacity !== null && Arr::get($mode, 'Alpha') !== null)
            ? $this->roundStat(Arr::get($mode, 'Alpha') * (float) $capacity)
            : null;

        $summary = [
            'FireMode' => Arr::get($mode, 'Name'),
            'PelletsPerShot' => Arr::get($mode, 'PelletsPerShot'),
            'Spread' => Arr::get($mode, 'Spread'),
            'AdsSpread' => Arr::get($mode, 'AdsSpread'),
            'Damage' => [
                'Burst' => $mode['Dps'] ?? $mode['DamagePerSecond'] ?? null,
                'AlphaTotal' => Arr::get($mode, 'Alpha'),
                'DpsTotal' => Arr::get($mode, 'Dps'),
                'MaxPerMag' => $maxPerMag,
                'Alpha' => $alpha,
                'Dps' => $dps,
            ],
            'Charge' => Arr::get($mode, 'Charge'),
            'ChargeModifier' => Arr::get($mode, 'ChargeModifier'),
        ];

        return $this->removeNullValues(array_merge($base, $summary));
    }
}
