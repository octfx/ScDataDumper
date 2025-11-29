<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class DamageResistance extends BaseFormat
{
    protected ?string $elementKey = '/damageResistance';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $resistance = $this->get();

        return [
            'Impact' => $this->item->get('impactForceResistance@impactForceResistance'),
            'Physical' => [
                'Multiplier' => $resistance->get('PhysicalResistance@Multiplier'),
                'Threshold' => $resistance->get('PhysicalResistance@Threshold'),
            ],
            'Energy' => [
                'Multiplier' => $resistance->get('EnergyResistance@Multiplier'),
                'Threshold' => $resistance->get('EnergyResistance@Threshold'),
            ],
            'Distortion' => [
                'Multiplier' => $resistance->get('DistortionResistance@Multiplier'),
                'Threshold' => $resistance->get('DistortionResistance@Threshold'),
            ],
            'Thermal' => [
                'Multiplier' => $resistance->get('ThermalResistance@Multiplier'),
                'Threshold' => $resistance->get('ThermalResistance@Threshold'),
            ],
            'Biochemical' => [
                'Multiplier' => $resistance->get('BiochemicalResistance@Multiplier'),
                'Threshold' => $resistance->get('BiochemicalResistance@Threshold'),
            ],
            'Stun' => [
                'Multiplier' => $resistance->get('StunResistance@Multiplier'),
                'Threshold' => $resistance->get('StunResistance@Threshold'),
            ],
        ];
    }
}
