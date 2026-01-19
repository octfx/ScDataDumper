<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class TractorBeam extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemWeaponComponentParams/fireActions/SWeaponActionFireTractorBeamParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $beam = $this->get();

        if ($beam === null) {
            return null;
        }

        $data = [
            'MinForce' => $beam->get('@minForce'),
            'MaxForce' => $beam->get('@maxForce'),
            'MinDistance' => $beam->get('@minDistance'),
            'MaxDistance' => $beam->get('@maxDistance'),
            'FullStrengthDistance' => $beam->get('@fullStrengthDistance'),
            'MaxAngle' => $beam->get('@maxAngle'),
            'MaxVolume' => $beam->get('@maxVolume'),
            'VolumeForceCoefficient' => $beam->get('@volumeForceCoefficient'),
            'TetherBreakTime' => $beam->get('@tetherBreakTime'),
            'SafeRangeValueFactor' => $beam->get('@safeRangeValueFactor'),
            'HitRadius' => $beam->get('@hitRadius'),
            'HeatPerSecond' => $beam->get('@heatPerSecond'),
            'WearPerSecond' => $beam->get('@wearPerSecond'),
            'MinEnergyDraw' => $beam->get('@minEnergyDraw'),
            'MaxEnergyDraw' => $beam->get('@maxEnergyDraw'),
            'AmmoType' => $beam->get('@ammoType'),
            'MaxPlayerLookRotationScale' => $beam->get('@maxPlayerLookRotationScale'),
            'AllowScrollingIntoBreakingRange' => $this->castBool($beam->get('@allowScrollingIntoBreakingRange')),
            'ShouldFireInHangars' => $this->castBool($beam->get('@shouldFireInHangars')),
            'ShouldTractorSelf' => $this->castBool($beam->get('@shouldTractorSelf')),
            'Input' => $beam->get('inputParams')?->attributesToArray(pascalCase: true) ?? [],
            'Movement' => $beam->get('movementParams')?->attributesToArray(pascalCase: true) ?? [],
            'AttachDetach' => $beam->get('attachDetachParams')?->attributesToArray(pascalCase: true) ?? [],
            'Rotation' => $beam->get('rotationParams')?->attributesToArray(pascalCase: true) ?? [],
            'Grapple' => $beam->get('grappleParams')?->attributesToArray(pascalCase: true) ?? [],
            'Vehicle' => $beam->get('vehicleParams')?->attributesToArray(pascalCase: true) ?? [],
            'MultiTractor' => $beam->get('multitractorParams')?->attributesToArray(pascalCase: true) ?? [],
            'BeamStrength' => $beam->get('beamStrengthValues')?->attributesToArray(pascalCase: true) ?? [],
            'CargoModeOverride' => $beam->get('attachDetachParams/cargoModeOverrideParams')?->attributesToArray(pascalCase: true) ?? [],
            'Towing' => $beam->get('towingBeamParams/SWeaponActionFireTractorBeamTowingParams')?->attributesToArray(pascalCase: true) ?? [],
        ];

        return $this->removeNullValues($data);
    }

    public function canTransform(): bool
    {
        return ($this->item?->getAttachType() === 'TractorBeam' || $this->item?->getAttachType() === 'TowingBeam') && parent::canTransform();
    }

    private function castBool(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return (bool) $value;
    }
}
