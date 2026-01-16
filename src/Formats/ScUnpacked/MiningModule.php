<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\Formats\BaseFormat;

/**
 * Mining Modules and Gadgets.
 */
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

        $weaponModifier = null;
        foreach ($modifiersRoot?->children() ?? [] as $node) {
            if ($node->getNode()->nodeName !== 'ItemWeaponModifiersParams') {
                continue;
            }

            if ((int) $node->get('@showInUI') === 1) {
                $weaponModifier = $node->get('/weaponModifier/weaponStats');
                break;
            }
        }

        $data = [
            'Type' => $lifetime !== null ? 'Active' : 'Passive',
            'Charges' => $charges,
            'Lifetime' => $lifetime,
            'Modifiers' => [
                'AllChargeRates' => $filterParams?->get('filterModifier/FloatModifierMultiplicative@value'),
                'ClusterFactor' => $miningModifiers?->get('clusterFactorModifier/FloatModifierMultiplicative@value'),
                // Mining Power for active modules, Extraction for passive
                'DamageMultiplier' => $weaponModifier?->get('@damageMultiplier'),
                'DamageMultiplierChange' => $weaponModifier ? round($weaponModifier->get('@damageMultiplier', 0) - 1, 2) : null,
                'Instability' => $miningModifiers?->get('laserInstability/FloatModifierMultiplicative@value'),
                'OptimalChargeRate' => $miningModifiers?->get('optimalChargeWindowRateModifier/FloatModifierMultiplicative@value'),
                'OptimalChargeWindow' => $miningModifiers?->get('optimalChargeWindowSizeModifier/FloatModifierMultiplicative@value'),
                'OverchargeRate' => $miningModifiers?->get('catastrophicChargeWindowRateModifier/FloatModifierMultiplicative@value'),
                'Resistance' => $miningModifiers?->get('resistanceModifier/FloatModifierMultiplicative@value'),
                'ShatterDamage' => $miningModifiers?->get('shatterdamageModifier/FloatModifierMultiplicative@value'),
                'InertMaterials' => $filterParams?->get('filterModifier/FloatModifierMultiplicative@value'),
                'Extract' => $miningModifiers?->get('filterModifier/FloatModifierMultiplicative@value'),
            ],
        ];

        return $this->removeNullValues($data);
    }

    public function canTransform(): bool
    {
        return ($this->item?->getAttachType() === 'MiningModifier' || $this->item?->getAttachType() === 'Gadget')
            && $this->has($this->elementKey);
    }
}
