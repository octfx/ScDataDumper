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
        $miningModifiers = $modifiersRoot?->get('ItemMiningModifierParams/MiningLaserModifier');
        $filterParams = $modifiersRoot?->get('MiningFilterItemModifierParams/filterParams');

        $lifetime = $modifiersRoot?->get('ItemMiningModifierParams/modifierLifetime/ItemModifierTimedLife@lifetime');
        $type = $lifetime !== null ? 'Active' : 'Passive';

        $data = [
            'Type' => $type,
            'Modifiers' => [
                'AllChargeRates' => $filterParams?->get('filterModifier/FloatModifierMultiplicative@value'),
                'CollectionPointRadius' => null,
                'Instability' => $miningModifiers?->get('laserInstability/FloatModifierMultiplicative@value'),
                'Module' => null,
                'OptimalChargeRate' => $miningModifiers?->get('optimalChargeWindowRateModifier/FloatModifierMultiplicative@value'),
                'OptimalChargeWindow' => $miningModifiers?->get('optimalChargeWindowSizeModifier/FloatModifierMultiplicative@value'),
                'OverchargeRate' => $miningModifiers?->get('catastrophicChargeWindowRateModifier/FloatModifierMultiplicative@value'),
                'Resistance' => $miningModifiers?->get('resistanceModifier/FloatModifierMultiplicative@value'),
                'ShatterDamage' => $miningModifiers?->get('shatterdamageModifier/FloatModifierMultiplicative@value'),
                'ThrottleResponsivenessDelay' => null,
                'ThrottleSpeed' => null,
                'ExtractionRate' => null,
                'InertMaterials' => $filterParams?->get('filterModifier/FloatModifierMultiplicative@value'),
                'ModuleSlots' => null,
            ],
        ];

        $description = $this->item->get('Components/SAttachableComponentParams/AttachDef/Localization/English@Description', '') ?? '';

        $descriptionData = ItemDescriptionParser::parse($description, [
            'Item Type' => 'type',
            'All Charge Rates' => 'AllChargeRates',
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

        // Fill missing values from description-derived data
        $data['Type'] ??= $parsed['type'] ?? null;

        foreach ($data['Modifiers'] as $key => $value) {
            if ($value === null && array_key_exists($key, $parsed)) {
                $data['Modifiers'][$key] = $parsed[$key];
            }
        }

        return $this->removeNullValues($data);
    }

    public function canTransform(): bool
    {
        return $this->item?->getAttachType() === 'MiningModifier'
            && $this->has($this->elementKey);
    }
}
