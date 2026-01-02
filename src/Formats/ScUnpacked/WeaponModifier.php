<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;

final class WeaponModifier extends BaseFormat
{
    protected ?string $elementKey = 'Components/SWeaponModifierComponentParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $component = $this->get();
        $weaponStats = $component?->get('/modifier/weaponStats');

        if ($weaponStats === null) {
            return null;
        }

        $data = [
            ...$this->buildMetadata($component),
            'WeaponStats' => [
                'Base' => $this->buildBaseStats($weaponStats),
                'Recoil' => $this->buildRecoil($weaponStats->get('/recoilModifier')),
                'Spread' => $this->buildSpread($weaponStats->get('/spreadModifier')),
                'Aim' => $this->buildAim($weaponStats->get('/aimModifier')),
                'Regen' => $this->buildRegen($weaponStats->get('/regenModifier')),
                'Salvage' => $this->buildSalvage($weaponStats->get('/salvageModifier')),
            ],
            'Zeroing' => $this->buildZeroing($component->get('/zeroingParams/SWeaponZeroingParams')),
            'Reticle' => $this->buildReticle($component->get('/reticleParams/SWeaponReticleParams')),
        ];

        return $this->removeNullValues($data);
    }

    public function canTransform(): bool
    {
        return $this->item !== null
            && $this->has($this->elementKey ?? '')
            && $this->item->get('Components/SWeaponModifierComponentParams/modifier/weaponStats') !== null;
    }

    private function buildMetadata(Element $component): array
    {
        return $this->mapAttributes($component, [
            'activateOnAttach' => 'ActivateOnAttach',
            'ignoreWear' => 'IgnoreWear',
        ]);
    }

    private function buildBaseStats(Element $weaponStats): array
    {
        return [
            'MuzzleFlash' => $this->get()?->get('@barrelEffectsStrength'),
        ] + $this->mapAttributes($weaponStats, [
            'fireRate' => 'FireRate',
            'fireRateMultiplier' => 'FireRateMultiplier',
            'damageMultiplier' => 'DamageMultiplier',
            'damageOverTimeMultiplier' => 'DamageOverTimeMultiplier',
            'projectileSpeedMultiplier' => 'ProjectileSpeedMultiplier',
            'pellets' => 'Pellets',
            'burstShots' => 'BurstShots',
            'ammoCost' => 'AmmoCost',
            'ammoCostMultiplier' => 'AmmoCostMultiplier',
            'heatGenerationMultiplier' => 'HeatGenerationMultiplier',
            'soundRadiusMultiplier' => 'SoundRadiusMultiplier',
            'chargeTimeMultiplier' => 'ChargeTimeMultiplier',
            'useAugmentedRealityProjectiles' => 'UseAugmentedRealityProjectiles',
        ]);
    }

    private function buildRecoil(?Element $recoil): array
    {
        if ($recoil === null) {
            return [];
        }

        $data = $this->mapAttributes($recoil, [
            'decayMultiplier' => 'DecayMultiplier',
            'endDecayMultiplier' => 'EndDecayMultiplier',
            'fireRecoilTimeMultiplier' => 'FireRecoilTimeMultiplier',
            'fireRecoilStrengthFirstMultiplier' => 'FireRecoilStrengthFirstMultiplier',
            'fireRecoilStrengthMultiplier' => 'FireRecoilStrengthMultiplier',
            'angleRecoilStrengthMultiplier' => 'AngleRecoilStrengthMultiplier',
            'randomnessMultiplier' => 'RandomnessMultiplier',
            'randomnessBackPushMultiplier' => 'RandomnessBackPushMultiplier',
            'frontalOscillationRotationMultiplier' => 'FrontalOscillationRotationMultiplier',
            'frontalOscillationStrengthMultiplier' => 'FrontalOscillationStrengthMultiplier',
            'frontalOscillationDecayMultiplier' => 'FrontalOscillationDecayMultiplier',
            'frontalOscillationRandomnessMultiplier' => 'FrontalOscillationRandomnessMultiplier',
            'animatedRecoilMultiplier' => 'AnimatedRecoilMultiplier',
        ]);

        $data['AimRecoil'] = $this->buildAimRecoil($recoil->get('aimRecoilModifier'));

        return $this->removeNullValues($data);
    }

    private function buildAimRecoil(?Element $aim): array
    {
        if ($aim === null) {
            return [];
        }

        $data = $this->mapAttributes($aim, [
            'randomPitchMultiplier' => 'RandomPitchMultiplier',
            'randomYawMultiplier' => 'RandomYawMultiplier',
            'decayMultiplier' => 'DecayMultiplier',
            'endDecayMultiplier' => 'EndDecayMultiplier',
        ]);

        $data['MaxMultiplier'] = $this->buildVector($aim->get('maxMultiplier'), ['x' => 'X', 'y' => 'Y']);
        $data['ShotKickFirstMultiplier'] = $this->buildVector($aim->get('shotKickFirstMultiplier'), ['x' => 'X', 'y' => 'Y']);
        $data['ShotKickMultiplier'] = $this->buildVector($aim->get('shotKickMultiplier'), ['x' => 'X', 'y' => 'Y']);
        $data['CurveRecoil'] = $this->buildAimRecoilCurve($aim->get('curveRecoil'));

        return $this->removeNullValues($data);
    }

    private function buildAimRecoilCurve(?Element $curve): array
    {
        if ($curve === null) {
            return [];
        }

        $data = $this->mapAttributes($curve, [
            'yawMaxDegreesModifier' => 'YawMaxDegreesModifier',
            'pitchMaxDegreesModifier' => 'PitchMaxDegreesModifier',
            'rollMaxDegreesModifier' => 'RollMaxDegreesModifier',
            'maxFireTimeModifier' => 'MaxFireTimeModifier',
            'recoilSmoothTimeModifier' => 'RecoilSmoothTimeModifier',
            'decayStartTimeModifier' => 'DecayStartTimeModifier',
            'minDecayTimeModifier' => 'MinDecayTimeModifier',
            'maxDecayTimeModifier' => 'MaxDecayTimeModifier',
        ]);

        $data['MinLimitsModifier'] = $this->buildVector($curve->get('minLimitsModifier'), ['x' => 'X', 'y' => 'Y', 'z' => 'Z']);
        $data['MaxLimitsModifier'] = $this->buildVector($curve->get('maxLimitsModifier'), ['x' => 'X', 'y' => 'Y', 'z' => 'Z']);

        return $this->removeNullValues($data);
    }

    private function buildSpread(?Element $spread): array
    {
        if ($spread === null) {
            return [];
        }

        return $this->removeNullValues($this->mapAttributes($spread, [
            'minMultiplier' => 'MinMultiplier',
            'maxMultiplier' => 'MaxMultiplier',
            'firstAttackMultiplier' => 'FirstAttackMultiplier',
            'attackMultiplier' => 'AttackMultiplier',
            'decayMultiplier' => 'DecayMultiplier',
            'additiveModifier' => 'AdditiveModifier',
        ]));
    }

    private function buildAim(?Element $aim): array
    {
        if ($aim === null) {
            return [];
        }

        return $this->removeNullValues($this->mapAttributes($aim, [
            'zoomScale' => 'ZoomScale',
            'secondZoomScale' => 'SecondZoomScale',
            'zoomTimeScale' => 'ZoomTimeScale',
            'hideWeaponInADS' => 'HideWeaponInAds',
            'fstopMultiplier' => 'FstopMultiplier',
        ]));
    }

    private function buildRegen(?Element $regen): array
    {
        if ($regen === null) {
            return [];
        }

        return $this->removeNullValues($this->mapAttributes($regen, [
            'powerRatioMultiplier' => 'PowerRatioMultiplier',
            'maxAmmoLoadMultiplier' => 'MaxAmmoLoadMultiplier',
            'maxRegenPerSecMultiplier' => 'MaxRegenPerSecMultiplier',
        ]));
    }

    private function buildSalvage(?Element $salvage): array
    {
        if ($salvage === null) {
            return [];
        }

        return $this->removeNullValues($this->mapAttributes($salvage, [
            'salvageSpeedMultiplier' => 'SalvageSpeedMultiplier',
            'radiusMultiplier' => 'RadiusMultiplier',
            'extractionEfficiency' => 'ExtractionEfficiency',
        ]));
    }

    private function buildZeroing(?Element $zeroing): array
    {
        if ($zeroing === null) {
            return [];
        }

        return $this->removeNullValues($this->mapAttributes($zeroing, [
            'defaultRange' => 'DefaultRange',
            'maxRange' => 'MaxRange',
            'rangeIncrement' => 'RangeIncrement',
            'autoZeroingTime' => 'AutoZeroingTime',
        ]));
    }

    private function buildReticle(?Element $reticle): array
    {
        if ($reticle === null) {
            return [];
        }

        return $this->removeNullValues($this->mapAttributes($reticle, [
            'defaultReticle' => 'DefaultReticle',
            'adsReticle' => 'AdsReticle',
        ]));
    }

    /**
     * @param  array<string, string>  $map
     */
    private function mapAttributes(Element $element, array $map): array
    {
        $out = [];

        foreach ($map as $attribute => $key) {
            $value = $element->get($attribute);

            if ($value !== null) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $names
     */
    private function buildVector(?Element $node, array $names): ?array
    {
        if ($node === null) {
            return null;
        }

        $out = [];

        foreach ($names as $attribute => $key) {
            $value = $node->get($attribute);

            if ($value !== null) {
                $out[$key] = $value;
            }
        }

        return empty($out) ? null : $out;
    }
}
