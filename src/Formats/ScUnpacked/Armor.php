<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class Armor extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemVehicleArmorParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $armor = $this->get();

        return [
            'DamageMultipliers' => [
                'Physical' => $armor->get('damageMultiplier/DamageInfo@DamagePhysical'),
                'Energy' => $armor->get('damageMultiplier/DamageInfo@DamageEnergy'),
                'Distortion' => $armor->get('damageMultiplier/DamageInfo@DamageDistortion'),
                'Thermal' => $armor->get('damageMultiplier/DamageInfo@DamageThermal'),
                'Biochemical' => $armor->get('damageMultiplier/DamageInfo@DamageBiochemical'),
                'Stun' => $armor->get('damageMultiplier/DamageInfo@DamageStun'),
            ],
            'SignalMultipliers' => [
                'CrossSection' => $armor->get('signalCrossSection'),
                'Infrared' => $armor->get('signalInfrared'),
                'Electromagnetic' => $armor->get('signalElectromagnetic'),
            ],
            'PenetrationResistance' => [
                'Base' => $armor->get('armorPenetrationResistance@basePenetrationReduction'),
                'Physical' => $armor->get('armorPenetrationResistance/penetrationAbsorptionForType@DamagePhysical'),
                'Energy' => $armor->get('armorPenetrationResistance/penetrationAbsorptionForType@DamageEnergy'),
                'Distortion' => $armor->get('armorPenetrationResistance/penetrationAbsorptionForType@DamageDistortion'),
                'Thermal' => $armor->get('armorPenetrationResistance/penetrationAbsorptionForType@DamageThermal'),
                'Biochemical' => $armor->get('armorPenetrationResistance/penetrationAbsorptionForType@DamageBiochemical'),
                'Stun' => $armor->get('armorPenetrationResistance/penetrationAbsorptionForType@DamageStun'),
            ],
        ];
    }

    public function canTransform(): bool
    {
        return $this->item?->getAttachType() === 'Armor' && parent::canTransform();
    }
}
