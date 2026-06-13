<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Helper\Element;

final class WeaponModifier extends BaseFormat
{
    private const string WEAPON_MODIFIER_COMPONENT = 'Components/SWeaponModifierComponentParams';
    private const string ATTACHABLE_MODIFIER_COMPONENT = 'Components/EntityComponentAttachableModifierParams';

    /** Component path resolved by canTransform() */
    private ?string $resolvedComponentPath = null;

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $component = $this->get($this->resolvedComponentPath);
        $weaponStats = $this->resolveWeaponStats($component);

        if ($weaponStats === null) {
            return null;
        }

        $isAttachable = $this->resolvedComponentPath === self::ATTACHABLE_MODIFIER_COMPONENT;

        $data = [
            ...$this->buildMetadata($component, $isAttachable),
            'WeaponStats' => [
                'Base' => $this->buildBaseStats($weaponStats),
                'Recoil' => $this->buildRecoil($weaponStats->get('recoilModifier')),
                'Spread' => $this->buildSpread($weaponStats->get('spreadModifier')),
                'Aim' => $this->buildAim($weaponStats->get('aimModifier')),
                'Regen' => $this->buildRegen($weaponStats->get('regenModifier')),
                'Salvage' => $this->buildSalvage($weaponStats->get('salvageModifier')),
            ],
        ];

        if (! $isAttachable) {
            $data['Zeroing'] = $this->buildZeroing($component->get('zeroingParams/SWeaponZeroingParams'));
            $data['Reticle'] = $this->buildReticle($component->get('reticleParams/SWeaponReticleParams'));
        }

        return $this->removeNullValues($data);
    }

    public function canTransform(): bool
    {
        if ($this->item === null) {
            return false;
        }

        // Standard weapon modifier (barrels, power arrays, etc.)
        if ($this->item->get(self::WEAPON_MODIFIER_COMPONENT.'/modifier/weaponStats') !== null) {
            $this->resolvedComponentPath = self::WEAPON_MODIFIER_COMPONENT;

            return true;
        }

        // Salvage modifier modules (attachable modifier with weaponStats)
        if ($this->item->get(self::ATTACHABLE_MODIFIER_COMPONENT.'/modifiers/ItemWeaponModifiersParams/weaponModifier/weaponStats') !== null) {
            $this->resolvedComponentPath = self::ATTACHABLE_MODIFIER_COMPONENT;

            return true;
        }

        return false;
    }

    /**
     * Resolve the weaponStats element from either component type.
     *
     * - SWeaponModifierComponentParams -> modifier/weaponStats
     * - EntityComponentAttachableModifierParams -> modifiers/ItemWeaponModifiersParams/weaponModifier/weaponStats
     */
    private function resolveWeaponStats(?Element $component): ?Element
    {
        if ($component === null) {
            return null;
        }

        if ($this->resolvedComponentPath === self::ATTACHABLE_MODIFIER_COMPONENT) {
            return $component->get('modifiers/ItemWeaponModifiersParams/weaponModifier/weaponStats');
        }

        return $component->get('modifier/weaponStats');
    }

    private function buildMetadata(Element $component, bool $isAttachable): array
    {
        if ($isAttachable) {
            return $this->mapAttributes($component, [
                '@activationMethod' => 'ActivationMethod',
                '@charges' => 'Charges',
                '@canInterrupt' => 'CanInterrupt',
                '@isInterruptible' => 'IsInterruptible',
            ]);
        }

        return $this->mapAttributes($component, [
            '@activateOnAttach' => 'ActivateOnAttach',
            '@ignoreWear' => 'IgnoreWear',
        ]);
    }

    private function buildBaseStats(Element $weaponStats): array
    {
        $component = $this->get($this->resolvedComponentPath);
        $effectStrength = $component?->get('@barrelEffectsStrength');

        return [
            'MuzzleFlashScale' => $effectStrength,
            ...($this->getFlashModifiers($component) ?? []),
        ] + $this->mapAttributes($weaponStats, [
            '@fireRateMultiplier' => 'FireRateMultiplier',
            '@damageMultiplier' => 'DamageMultiplier',
            '@projectileSpeedMultiplier' => 'ProjectileSpeedMultiplier',
            '@ammoCostMultiplier' => 'AmmoCostMultiplier',
            '@heatGenerationMultiplier' => 'HeatGenerationMultiplier',
            '@soundRadiusMultiplier' => 'SoundRadiusMultiplier',
            '@chargeTimeMultiplier' => 'ChargeTimeMultiplier',
        ]);
    }

    private function getFlashModifiers(?Element $component): ?array
    {
        $fireEffects = $component?->get('fireEffects');

        if ($fireEffects === null) {
            return null;
        }

        foreach ($fireEffects->children() ?? [] as $effect) {
            if ($effect->nodeName !== 'SWeaponParticleEffectParams') {
                continue;
            }

            $scale = $effect->get('@scale');
            if ($scale !== null) {
                return [
                    'MuzzleFlashScale' => $scale,
                    'MuzzleFlashDelay' => $effect->get('@delay'),
                ];
            }
        }

        return null;
    }

    private function buildRecoil(?Element $recoil): array
    {
        if ($recoil === null) {
            return [];
        }

        $data = $this->mapAttributes($recoil, [
            '@decayMultiplier' => 'DecayMultiplier',
            '@endDecayMultiplier' => 'EndDecayMultiplier',
            '@fireRecoilTimeMultiplier' => 'FireRecoilTimeMultiplier',
            '@fireRecoilStrengthFirstMultiplier' => 'FireRecoilStrengthFirstMultiplier',
            '@fireRecoilStrengthMultiplier' => 'FireRecoilStrengthMultiplier',
            '@angleRecoilStrengthMultiplier' => 'AngleRecoilStrengthMultiplier',
            '@randomnessMultiplier' => 'RandomnessMultiplier',
            '@randomnessBackPushMultiplier' => 'RandomnessBackPushMultiplier',
            '@frontalOscillationRotationMultiplier' => 'FrontalOscillationRotationMultiplier',
            '@frontalOscillationStrengthMultiplier' => 'FrontalOscillationStrengthMultiplier',
            '@frontalOscillationDecayMultiplier' => 'FrontalOscillationDecayMultiplier',
            '@frontalOscillationRandomnessMultiplier' => 'FrontalOscillationRandomnessMultiplier',
            '@animatedRecoilMultiplier' => 'AnimatedRecoilMultiplier',
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
            '@randomPitchMultiplier' => 'RandomPitchMultiplier',
            '@randomYawMultiplier' => 'RandomYawMultiplier',
            '@decayMultiplier' => 'DecayMultiplier',
            '@endDecayMultiplier' => 'EndDecayMultiplier',
        ]);

        $data['MaxMultiplier'] = $this->buildVector($aim->get('maxMultiplier'), ['@x' => 'X', '@y' => 'Y']);
        $data['ShotKickFirstMultiplier'] = $this->buildVector($aim->get('shotKickFirstMultiplier'), ['@x' => 'X', '@y' => 'Y']);
        $data['ShotKickMultiplier'] = $this->buildVector($aim->get('shotKickMultiplier'), ['@x' => 'X', '@y' => 'Y']);
        $data['CurveRecoil'] = $this->buildAimRecoilCurve($aim->get('curveRecoil'));

        return $this->removeNullValues($data);
    }

    private function buildAimRecoilCurve(?Element $curve): array
    {
        if ($curve === null) {
            return [];
        }

        $data = $this->mapAttributes($curve, [
            '@yawMaxDegreesModifier' => 'YawMaxDegreesModifier',
            '@pitchMaxDegreesModifier' => 'PitchMaxDegreesModifier',
            '@rollMaxDegreesModifier' => 'RollMaxDegreesModifier',
            '@maxFireTimeModifier' => 'MaxFireTimeModifier',
            '@recoilSmoothTimeModifier' => 'RecoilSmoothTimeModifier',
            '@decayStartTimeModifier' => 'DecayStartTimeModifier',
            '@minDecayTimeModifier' => 'MinDecayTimeModifier',
            '@maxDecayTimeModifier' => 'MaxDecayTimeModifier',
        ]);

        $data['MinLimitsModifier'] = $this->buildVector($curve->get('minLim0tsModifier'), ['@x' => 'X', '@y' => 'Y', '@z' => 'Z']);
        $data['MaxLimitsModifier'] = $this->buildVector($curve->get('maxLim0tsModifier'), ['@x' => 'X', '@y' => 'Y', '@z' => 'Z']);

        return $this->removeNullValues($data);
    }

    private function buildSpread(?Element $spread): array
    {
        if ($spread === null) {
            return [];
        }

        return $this->removeNullValues($this->mapAttributes($spread, [
            '@minMultiplier' => 'MinMultiplier',
            '@maxMultiplier' => 'MaxMultiplier',
            '@firstAttackMultiplier' => 'FirstAttackMultiplier',
            '@attackMultiplier' => 'AttackMultiplier',
            '@decayMultiplier' => 'DecayMultiplier',
            '@additiveModifier' => 'AdditiveModifier',
        ]));
    }

    private function buildAim(?Element $aim): array
    {
        if ($aim === null) {
            return [];
        }

        return $this->removeNullValues($this->mapAttributes($aim, [
            '@zoomScale' => 'ZoomScale',
            '@secondZoomScale' => 'SecondZoomScale',
            '@zoomTimeScale' => 'ZoomTimeScale',
            '@hideWeaponInADS' => 'HideWeaponInAds',
            '@fstopMultiplier' => 'FstopMultiplier',
        ]));
    }

    private function buildRegen(?Element $regen): array
    {
        if ($regen === null) {
            return [];
        }

        return $this->removeNullValues($this->mapAttributes($regen, [
            '@powerRatioMultiplier' => 'PowerRatioMultiplier',
            '@maxAmmoLoadMultiplier' => 'MaxAmmoLoadMultiplier',
            '@maxRegenPerSecMultiplier' => 'MaxRegenPerSecMultiplier',
        ]));
    }

    private function buildSalvage(?Element $salvage): array
    {
        if ($salvage === null) {
            return [];
        }

        return $this->removeNullValues($this->mapAttributes($salvage, [
            '@salvageSpeedMultiplier' => 'SalvageSpeedMultiplier',
            '@radiusMultiplier' => 'RadiusMultiplier',
            '@extractionEfficiency' => 'ExtractionEfficiency',
        ]));
    }

    private function buildZeroing(?Element $zeroing): array
    {
        if ($zeroing === null) {
            return [];
        }

        return $this->removeNullValues($this->mapAttributes($zeroing, [
            '@defaultRange' => 'DefaultRange',
            '@maxRange' => 'MaxRange',
            '@rangeIncrement' => 'RangeIncrement',
            '@autoZeroingTime' => 'AutoZeroingTime',
        ]));
    }

    private function buildReticle(?Element $reticle): array
    {
        if ($reticle === null) {
            return [];
        }

        return $this->removeNullValues($this->mapAttributes($reticle, [
            '@defaultReticle' => 'DefaultReticle',
            '@adsReticle' => 'AdsReticle',
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
