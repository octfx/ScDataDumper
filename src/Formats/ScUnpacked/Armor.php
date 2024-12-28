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
        ];
    }

    public function canTransform(): bool
    {
        return $this->item?->getAttachType() === 'Armor' && parent::canTransform();
    }
}
