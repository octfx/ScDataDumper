<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Formats\BaseFormat;

/**
 * @extends BaseFormat<EntityClassDefinition>
 */
final class Armor extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemVehicleArmorParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $armor = $this->get();

        $damageMultipliers = [];
        foreach (self::$resistanceKeys as $key) {
            $damageMultipliers[$key] = $armor->get('damageMultiplier/DamageInfo@Damage'.$key);
            $damageMultipliers[$key.'Change'] = round($armor->get('damageMultiplier/DamageInfo@Damage'.$key, 1) - 1, 2);
        }

        $penetrationResistance = [
            'Base' => $armor->get('armorPenetrationResistance@basePenetrationReduction'),
        ];
        foreach (self::$resistanceKeys as $key) {
            $penetrationResistance[$key] = $armor->get('armorPenetrationResistance/penetrationAbsorptionForType@Damage'.$key);
        }

        $deflection = [];
        foreach (self::$resistanceKeys as $key) {
            $deflection[$key] = $armor->get('armorDeflection/deflectionValue@Damage'.$key);
        }

        return [
            'DamageMultipliers' => $damageMultipliers,
            'SignalMultipliers' => [
                'CrossSection' => $armor->get('@signalCrossSection'),
                'CrossSectionChange' => round($armor->get('@signalCrossSection', 1) - 1, 2),
                'Infrared' => $armor->get('@signalInfrared'),
                'InfraredChange' => round($armor->get('@signalInfrared', 1) - 1, 2),
                'Electromagnetic' => $armor->get('@signalElectromagnetic'),
                'ElectromagneticChange' => round($armor->get('@signalElectromagnetic', 1) - 1, 2),
            ],
            'PenetrationResistance' => $penetrationResistance,
            'Deflection' => $deflection,
        ];
    }

    public function canTransform(): bool
    {
        return $this->item?->getAttachType() === 'Armor' && parent::canTransform();
    }
}
