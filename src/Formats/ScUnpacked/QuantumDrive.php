<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class QuantumDrive extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemQuantumDriveParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $quantum = $this->get();

        return [
            'JumpRange' => $quantum->get('jumpRange'),
            'disconnectRange' => $quantum->get('disconnectRange'),
            'FuelRate' => $quantum->get('quantumFuelRequirement') / 1e6,
            'StandardJump' => new JumpPerformance($quantum->get('/params')),
            'SplineJump' => new JumpPerformance($quantum->get('/splineJumpParams')),
            'Heat' => [
                'preRampUpThermalEnergyDraw' => $quantum->get('heatParams@preRampUpThermalEnergyDraw'),
                'rampUpThermalEnergyDraw' => $quantum->get('heatParams@rampUpThermalEnergyDraw'),
                'inFlightThermalEnergyDraw' => $quantum->get('heatParams@inFlightThermalEnergyDraw'),
                'rampDownThermalEnergyDraw' => $quantum->get('heatParams@rampDownThermalEnergyDraw'),
                'postRampDownThermalEnergyDraw' => $quantum->get('heatParams@postRampDownThermalEnergyDraw'),
            ],
            'Boost' => [
                'maxBoostSpeed' => $quantum->get('quantumBoostParams@maxBoostSpeed'),
                'timeToMaxBoostSpeed' => $quantum->get('quantumBoostParams@timeToMaxBoostSpeed'),
                'boostUseTime' => $quantum->get('quantumBoostParams@boostUseTime'),
                'boostRechargeTime' => $quantum->get('quantumBoostParams@boostRechargeTime'),
                'stopTime' => $quantum->get('quantumBoostParams@stopTime'),
                'minJumpDistance' => $quantum->get('quantumBoostParams@minJumpDistance'),
                'ifcsHandoverDownTime' => $quantum->get('quantumBoostParams@ifcsHandoverDownTime'),
                'ifcsHandoverRespoolTime' => $quantum->get('quantumBoostParams@ifcsHandoverRespoolTime'),
            ],
        ];
    }
}
