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
                'PhysicalChange' => round($armor->get('damageMultiplier/DamageInfo@DamagePhysical', 1) - 1, 2),
                'Energy' => $armor->get('damageMultiplier/DamageInfo@DamageEnergy'),
                'EnergyChange' => round($armor->get('damageMultiplier/DamageInfo@DamageEnergy', 1) - 1, 2),
                'Distortion' => $armor->get('damageMultiplier/DamageInfo@DamageDistortion'),
                'DistortionChange' => round($armor->get('damageMultiplier/DamageInfo@DamageDistortion', 1) - 1, 2),
                'Thermal' => $armor->get('damageMultiplier/DamageInfo@DamageThermal'),
                'ThermalChange' => round($armor->get('damageMultiplier/DamageInfo@DamageThermal', 1) - 1, 2),
                'Biochemical' => $armor->get('damageMultiplier/DamageInfo@DamageBiochemical'),
                'BiochemicalChange' => round($armor->get('damageMultiplier/DamageInfo@DamageBiochemical', 1) - 1, 2),
                'Stun' => $armor->get('damageMultiplier/DamageInfo@DamageStun'),
                'StunChange' => round($armor->get('damageMultiplier/DamageInfo@DamageStun', 1) - 1, 2),
            ],
            'SignalMultipliers' => [
                'CrossSection' => $armor->get('@signalCrossSection'),
                'CrossSectionChange' => round($armor->get('@signalCrossSection', 1) - 1, 2),
                'Infrared' => $armor->get('@signalInfrared'),
                'InfraredChange' => round($armor->get('@signalInfrared', 1) - 1, 2),
                'Electromagnetic' => $armor->get('@signalElectromagnetic'),
                'ElectromagneticChange' => round($armor->get('@signalElectromagnetic', 1) - 1, 2),
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
