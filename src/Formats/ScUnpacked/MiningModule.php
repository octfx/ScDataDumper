<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\Helper\ItemDescriptionParser;

final class MiningModule extends BaseFormat
{
    protected ?string $elementKey = 'Components/EntityComponentAttachableModifierParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        /** @var Element|null $component */
        $component = $this->get();
        $modifiersRoot = $component?->get('/modifiers');

        $miningParamsRoot = $modifiersRoot?->get('/ItemMiningModifierParams')
            ?? $modifiersRoot?->get('/ItemMineableRockModifierParams');

        $miningModifiers = $modifiersRoot?->get('/ItemMiningModifierParams/MiningLaserModifier')
            ?? $modifiersRoot?->get('/ItemMineableRockModifierParams/MiningLaserModifier');

        $filterParams = $modifiersRoot?->get('/MiningFilterItemModifierParams/filterParams');

        $charges = $component?->get('@charges');

        $lifetime = $miningParamsRoot?->get('/modifierLifetime/ItemModifierTimedLife@lifetime')
            ?? $modifiersRoot?->get('/ItemMiningModifierParams/modifierLifetime/ItemModifierTimedLife@lifetime')
            ?? $modifiersRoot?->get('/ItemMineableRockModifierParams/modifierLifetime/ItemModifierTimedLife@lifetime');

        $activationMethod = $component?->get('@activationMethod');
        $type = $this->inferType($activationMethod, $lifetime);

        $damageMultiplier = $modifiersRoot?->get('/ItemWeaponModifiersParams/weaponModifier/weaponStats@damageMultiplier');

        $data = [
            'Type' => $type,
            'Charges' => $charges,
            'Lifetime' => $lifetime,
            'Modifiers' => [
                'AllChargeRates' => $filterParams?->get('filterModifier/FloatModifierMultiplicative@value'),
                'ClusterFactor' => $miningModifiers?->get('clusterFactorModifier/FloatModifierMultiplicative@value'),
                'DamageMultiplier' => $damageMultiplier,
                'Instability' => $miningModifiers?->get('laserInstability/FloatModifierMultiplicative@value'),
                'OptimalChargeRate' => $miningModifiers?->get('optimalChargeWindowRateModifier/FloatModifierMultiplicative@value'),
                'OptimalChargeWindow' => $miningModifiers?->get('optimalChargeWindowSizeModifier/FloatModifierMultiplicative@value'),
                'OverchargeRate' => $miningModifiers?->get('catastrophicChargeWindowRateModifier/FloatModifierMultiplicative@value'),
                'Resistance' => $miningModifiers?->get('resistanceModifier/FloatModifierMultiplicative@value'),
                'ShatterDamage' => $miningModifiers?->get('shatterdamageModifier/FloatModifierMultiplicative@value'),
                'InertMaterials' => $filterParams?->get('filterModifier/FloatModifierMultiplicative@value'),
            ],
        ];

        $description = $this->item->get(
            'Components/SAttachableComponentParams/AttachDef/Localization/English@Description',
            ''
        ) ?? '';

        $descriptionData = ItemDescriptionParser::parse($description, [
            'Item Type' => 'type',
            'Charges' => 'Charges',
            'Duration' => 'Lifetime',
            'Lifetime' => 'Lifetime',
            'All Charge Rates' => 'AllChargeRates',
            'Cluster Factor' => 'ClusterFactor',
            'Damage' => 'DamageMultiplier',
            'Damage Multiplier' => 'DamageMultiplier',
            'Collection Point Radius' => 'CollectionPointRadius',
            'Instability' => 'Instability',
            'Module Slots' => 'ModuleSlots',
            'Module' => 'Module',
            'Optimal Charge Rate' => 'OptimalChargeRate',
            'Optimal Charge Window' => 'OptimalChargeWindow',
            'Overcharge Rate' => 'OverchargeRate',
            'Resistance' => 'Resistance',
            'Shatter Damage' => 'ShatterDamage',
            'Throttle Responsiveness Delay' => 'ThrottleResponsivenessDelay',
            'Throttle Speed' => 'ThrottleSpeed',
            'Extraction Rate' => 'ExtractionRate',
            'Inert Materials' => 'InertMaterials',
        ]);

        $parsed = $descriptionData['data'] ?? [];

        foreach ($parsed as $key => $value) {
            if ($key === 'type') {
                $data['Type'] ??= $value;
            } elseif ($key === 'Charges') {
                $data['Charges'] ??= $value;
            } elseif ($key === 'Lifetime') {
                $data['Lifetime'] ??= $value;
            } elseif (! array_key_exists($key, $data['Modifiers'])) {
                $data['Modifiers'][$key] = $value;
            }
        }

        return $this->removeNullValues($data);
    }

    public function canTransform(): bool
    {
        return ($this->item?->getAttachType() === 'MiningModifier' || $this->item?->getAttachType() === 'Gadget')
            && $this->has($this->elementKey);
    }

    private function inferType(?string $activationMethod, mixed $lifetime): string
    {
        if (is_string($activationMethod) && $activationMethod !== '') {
            $m = strtolower($activationMethod);

            if (str_contains($m, 'passive') || $m === 'none' || str_contains($m, 'alwayson')) {
                return 'Passive';
            }

            return 'Active';
        }

        return $lifetime !== null ? 'Active' : 'Passive';
    }
}
