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
        $weaponStats = $component?->get('modifier/weaponStats');

        if ($weaponStats === null) {
            return null;
        }

        $data = [
            //            'Metadata' => $this->buildMetadata($component),
            'WeaponStats' => [
                'Base' => $this->buildBaseStats($weaponStats),
                'Recoil' => $this->buildRecoil($weaponStats->get('recoilModifier')),
                'Spread' => $this->buildSpread($weaponStats->get('spreadModifier')),
                'Aim' => $this->buildAim($weaponStats->get('aimModifier')),
                'Regen' => $this->buildRegen($weaponStats->get('regenModifier')),
                'Salvage' => $this->buildSalvage($weaponStats->get('salvageModifier')),
            ],
            'Zeroing' => $this->buildZeroing($component->get('zeroingParams/SWeaponZeroingParams')),
            'Reticle' => $this->buildReticle($component->get('reticleParams/SWeaponReticleParams')),
            //            'AdsCameraOffset' => $this->buildVector($component->get('adsCameraOffset'), [
            //                'x' => 'X',
            //                'y' => 'Y',
            //                'z' => 'Z',
            //            ]),
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
            'uiReticleIndex' => 'UiReticleIndex',
            'aimHelperYOffset' => 'AimHelperYOffset',
            'adsNearClipPlaneMultiplier' => 'AdsNearClipPlaneMultiplier',
            'barrelEffectsStrength' => 'BarrelEffectsStrength',
            'activateOnAttach' => 'ActivateOnAttach',
            'ignoreWear' => 'IgnoreWear',
            'forceIronSightSetup' => 'ForceIronSightSetup',
        ]);
    }

    private function buildBaseStats(Element $weaponStats): array
    {
        return $this->mapAttributes($weaponStats, [
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
            'useAlternateProjectileVisuals' => 'UseAlternateProjectileVisuals',
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

        //        $data['HeadRotationMultiplier'] = $this->buildVector($recoil->get('headRotationMultiplier'), [
        //            'x' => 'X',
        //            'y' => 'Y',
        //            'z' => 'Z',
        //        ]);

        $data['AimRecoil'] = $this->buildAimRecoil($recoil->get('aimRecoilModifier'));
        //        $data['HandsRecoil'] = $this->buildHandsRecoil($recoil->get('curveRecoil'));
        //        $data['HeadRecoil'] = $this->buildHeadRecoil($recoil->get('curveRecoilHead'));

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

        //        $noise = $curve->get('noiseCurvesModifier');
        //        if ($noise instanceof Element) {
        //            $data['NoiseCurvesModifier'] = $this->mapAttributes($noise, [
        //                'yawNoiseMaxValueModifier' => 'YawNoiseMaxValueModifier',
        //                'pitchNoiseMaxValueModifier' => 'PitchNoiseMaxValueModifier',
        //                'rollNoiseMaxValueModifier' => 'RollNoiseMaxValueModifier',
        //            ]);
        //        }

        return $this->removeNullValues($data);
    }

    private function buildHandsRecoil(?Element $curve): array
    {
        if ($curve === null) {
            return [];
        }

        $data = $this->mapAttributes($curve, [
            'recoilTimeModifier' => 'RecoilTimeModifier',
            'minDecayTimeModifier' => 'MinDecayTimeModifier',
            'maxDecayTimeModifier' => 'MaxDecayTimeModifier',
        ]);

        $position = $curve->get('positionModifiers');
        if ($position instanceof Element) {
            $data['PositionModifiers'] = $this->buildXYZCurveModifier($position);
        }

        $rotation = $curve->get('rotationModifiers');
        if ($rotation instanceof Element) {
            $data['RotationModifiers'] = $this->buildXYZCurveModifier($rotation);
        }

        $positionDecay = $curve->get('positionDecayModifiers');
        if ($positionDecay instanceof Element) {
            $data['PositionDecayModifiers'] = $this->buildDecayModifiers($positionDecay);
        }

        $rotationDecay = $curve->get('rotationDecayModifiers');
        if ($rotationDecay instanceof Element) {
            $data['RotationDecayModifiers'] = $this->buildDecayModifiers($rotationDecay);
        }

        return $this->removeNullValues($data);
    }

    private function buildHeadRecoil(?Element $curveHead): array
    {
        if ($curveHead === null) {
            return [];
        }

        $data = $this->mapAttributes($curveHead, [
            'headRecoilTimeModifier' => 'HeadRecoilTimeModifier',
            'frequencyModifier' => 'FrequencyModifier',
            'smoothingSpeedModifier' => 'SmoothingSpeedModifier',
        ]);

        $positionModifier = $curveHead->get('positionModifier');
        if ($positionModifier instanceof Element) {
            $data['PositionModifier'] = [
                'OffsetModifier' => $this->buildVector($positionModifier->get('offsetModifier'), ['x' => 'X', 'y' => 'Y', 'z' => 'Z']),
                'NoiseModifier' => $this->buildVector($positionModifier->get('noiseModifier'), ['x' => 'X', 'y' => 'Y', 'z' => 'Z']),
            ];
        }

        $rotationModifier = $curveHead->get('rotationModifier');
        if ($rotationModifier instanceof Element) {
            $data['RotationModifier'] = [
                'OffsetModifier' => $this->buildVector($rotationModifier->get('offsetModifier'), ['x' => 'X', 'y' => 'Y', 'z' => 'Z']),
                'NoiseModifier' => $this->buildVector($rotationModifier->get('noiseModifier'), ['x' => 'X', 'y' => 'Y', 'z' => 'Z']),
            ];
        }

        return $this->removeNullValues($data);
    }

    private function buildXYZCurveModifier(Element $modifier): array
    {
        $data = $this->mapAttributes($modifier, [
            'xMaxValueModifier' => 'XMaxValueModifier',
            'yMaxValueModifier' => 'YMaxValueModifier',
            'zMaxValueModifier' => 'ZMaxValueModifier',
        ]);

        $data['MinLimitsModifier'] = $this->buildVector($modifier->get('minLimitsModifier'), ['x' => 'X', 'y' => 'Y', 'z' => 'Z']);
        $data['MaxLimitsModifier'] = $this->buildVector($modifier->get('maxLimitsModifier'), ['x' => 'X', 'y' => 'Y', 'z' => 'Z']);

        $noise = $modifier->get('noiseModifier');
        if ($noise instanceof Element) {
            $data['NoiseModifier'] = $this->mapAttributes($noise, [
                'xNoiseModifier' => 'XNoiseModifier',
                'yNoiseModifier' => 'YNoiseModifier',
                'zNoiseModifier' => 'ZNoiseModifier',
            ]);
        }

        return $this->removeNullValues($data);
    }

    private function buildDecayModifiers(Element $modifier): array
    {
        $data = [
            'DecayTimeMultiplierModifier' => $this->buildVector($modifier->get('decayTimeMultiplierModifier'), ['x' => 'X', 'y' => 'Y', 'z' => 'Z']),
            'DecayMaxValueModifier' => $this->buildVector($modifier->get('decayMaxValueModifier'), ['x' => 'X', 'y' => 'Y', 'z' => 'Z']),
            'DecayMinScalingFactorModifier' => $this->buildVector($modifier->get('decayMinScalingFactorModifier'), ['x' => 'X', 'y' => 'Y', 'z' => 'Z']),
        ];

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
