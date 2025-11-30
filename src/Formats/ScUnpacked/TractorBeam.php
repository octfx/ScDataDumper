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
            'Input' => $this->transformArrayKeysToPascalCase($beam->get('inputParams')?->attributesToArray() ?? []),
            'Movement' => $this->transformArrayKeysToPascalCase($beam->get('movementParams')?->attributesToArray() ?? []),
            'AttachDetach' => $this->transformArrayKeysToPascalCase($beam->get('attachDetachParams')?->attributesToArray() ?? []),
            'Rotation' => $this->transformArrayKeysToPascalCase($beam->get('rotationParams')?->attributesToArray() ?? []),
            'Grapple' => $this->transformArrayKeysToPascalCase($beam->get('grappleParams')?->attributesToArray() ?? []),
            'Vehicle' => $this->transformArrayKeysToPascalCase($beam->get('vehicleParams')?->attributesToArray() ?? []),
            'MultiTractor' => $this->transformArrayKeysToPascalCase($beam->get('multitractorParams')?->attributesToArray() ?? []),
            'BeamStrength' => $this->transformArrayKeysToPascalCase($beam->get('beamStrengthValues')?->attributesToArray() ?? []),
            'CargoModeOverride' => $this->transformArrayKeysToPascalCase(
                $beam->get('attachDetachParams/cargoModeOverrideParams')?->attributesToArray() ?? []
            ),
        ];

        return $this->removeNullValues($data);
    }

    public function canTransform(): bool
    {
        return $this->item?->getAttachType() === 'TractorBeam' && parent::canTransform();
    }

    private function castBool(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return (bool) $value;
    }
}
