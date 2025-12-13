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
            'DisconnectRange' => $quantum->get('disconnectRange'),
            'QuantumFuelRequirement' => $quantum->get('quantumFuelRequirement'),
            'FuelRate' => $this->formatFuelRate($quantum->get('quantumFuelRequirement')),
            'StandardJump' => new JumpPerformance($quantum->get('/params')),
            'SplineJump' => new JumpPerformance($quantum->get('/splineJumpParams')),
            'Heat' => $this->formatHeat($quantum->get('/heatParams')),
            'Boost' => $this->formatBoost($quantum->get('/quantumBoostParams')),
        ];
    }

    private function formatHeat($heatParams): ?array
    {
        if ($heatParams === null) {
            return null;
        }

        return [
            'PreRampUpThermalEnergyDraw' => $heatParams->get('preRampUpThermalEnergyDraw'),
            'RampUpThermalEnergyDraw' => $heatParams->get('rampUpThermalEnergyDraw'),
            'InFlightThermalEnergyDraw' => $heatParams->get('inFlightThermalEnergyDraw'),
            'RampDownThermalEnergyDraw' => $heatParams->get('rampDownThermalEnergyDraw'),
            'PostRampDownThermalEnergyDraw' => $heatParams->get('postRampDownThermalEnergyDraw'),
        ];
    }

    private function formatBoost($boostParams): ?array
    {
        if ($boostParams === null) {
            return null;
        }

        return [
            'MaxBoostSpeed' => $boostParams->get('maxBoostSpeed'),
            'TimeToMaxBoostSpeed' => $boostParams->get('timeToMaxBoostSpeed'),
            'BoostUseTime' => $boostParams->get('boostUseTime'),
            'BoostRechargeTime' => $boostParams->get('boostRechargeTime'),
            'StopTime' => $boostParams->get('stopTime'),
            'MinJumpDistance' => $boostParams->get('minJumpDistance'),
            'IfcsHandoverDownTime' => $boostParams->get('ifcsHandoverDownTime'),
            'IfcsHandoverRespoolTime' => $boostParams->get('ifcsHandoverRespoolTime'),
        ];
    }

    private function formatFuelRate(mixed $rawRequirement): ?float
    {
        if ($rawRequirement === null) {
            return null;
        }

        return $rawRequirement / 1e6;
    }
}
