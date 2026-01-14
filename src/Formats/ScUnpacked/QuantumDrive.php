<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class QuantumDrive extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemQuantumDriveParams';

    private const float DEFAULT_DISTANCE_GM = 10.0;

    private const float CONSUMPTION_MICRO_TO_SCU_PER_GM = 1000.0;

    /** 1 Gm = 1e9 meters. */
    private const float METERS_PER_GM = 1_000_000_000.0;

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $quantum = $this->get();

        $rawRequirement = $quantum->get('quantumFuelRequirement');
        $consumptionPerGm = $this->getFuelConsumptionPerGm($rawRequirement);

        $standardParams = $quantum->get('/params');
        $splineParams = $quantum->get('/splineJumpParams');
        $driveSpeed = $standardParams?->get('driveSpeed');

        $standardTravelSeconds10Gm = $this->estimateTravelTimeSeconds($standardParams, self::DEFAULT_DISTANCE_GM);

        return $quantum->attributesToArray([
            'tracePoint',
        ], true) + [
            'FuelRate' => $this->formatFuelRate($rawRequirement),
            'StandardJump' => new JumpPerformance($standardParams),
            'SplineJump' => new JumpPerformance($splineParams),
            'Heat' => $this->formatHeat($quantum->get('/heatParams')),
            'Boost' => $this->formatBoost($quantum->get('/quantumBoostParams')),

            'FuelConsumptionSCUPerGM' => $consumptionPerGm,
            'FuelEfficiencyGMPerSCU' => $this->formatFuelEfficiencyGmPerScu($consumptionPerGm, $driveSpeed),
            'FuelRequirement10GM' => $this->formatFuelForDistanceGm($consumptionPerGm, self::DEFAULT_DISTANCE_GM),
            'TravelTime10GMSeconds' => round($standardTravelSeconds10Gm),
            'TravelTime10GM' => $this->formatDuration($standardTravelSeconds10Gm),
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

        return (float) $rawRequirement / 1e6;
    }

    private function getFuelConsumptionPerGm(?float $rawRequirement): ?float
    {
        $microRate = $this->getQuantumFuelMicroUnitsPerSecond();
        if ($microRate !== null && $microRate > 0) {
            return $microRate / self::CONSUMPTION_MICRO_TO_SCU_PER_GM;
        }

        if ($rawRequirement === null) {
            return null;
        }

        $v = (float) $rawRequirement;

        return $v > 0 ? $v : null;
    }

    /**
     * Normalized efficiency derived from drive speed and consumption (10 GM baseline).
     */
    private function formatFuelEfficiencyGmPerScu(?float $consumptionPerGm, ?float $driveSpeed): ?float
    {
        if ($consumptionPerGm === null || $consumptionPerGm <= 0 || $driveSpeed === null || $driveSpeed <= 0) {
            return null;
        }

        $gmPerSecond = $driveSpeed / self::METERS_PER_GM;

        return round($gmPerSecond / ($consumptionPerGm * self::DEFAULT_DISTANCE_GM), 2);
    }

    /**
     * Fuel required for a given distance in Gm.
     */
    private function formatFuelForDistanceGm(?float $consumptionPerGm, float $distanceGm): ?float
    {
        if ($consumptionPerGm === null || $consumptionPerGm < 0 || $distanceGm < 0) {
            return null;
        }

        return round($consumptionPerGm * $distanceGm, 2);
    }

    /**
     * Estimate in-flight QT travel time for a given distance using the linear-ramp acceleration model:
     * - acceleration starts at a1 and increases linearly to a2 until vmax is reached
     * - for long trips: accelerate, cruise at vmax, decelerate symmetrically
     * - for short trips: never reach vmax; solve ramp-only distance for half-trip and double it
     */
    private function estimateTravelTimeSeconds($jumpParams, float $distanceGm): ?float
    {
        if ($jumpParams === null) {
            return null;
        }

        $vmax = (float) $jumpParams->get('driveSpeed');
        $a1 = (float) $jumpParams->get('stageOneAccelRate');
        $a2 = (float) $jumpParams->get('stageTwoAccelRate');

        if ($distanceGm <= 0) {
            return 0.0;
        }

        if ($vmax <= 0 || $a1 <= 0 || $a2 <= 0) {
            return null;
        }

        $distanceM = $distanceGm * self::METERS_PER_GM;

        // Time to reach vmax with linearly increasing acceleration (from a1 to a2)
        $tRamp = (2.0 * $vmax) / ($a1 + $a2);

        // Distance traveled during that ramp-up (same for ramp-down)
        $sumA = $a1 + $a2;
        $dRamp = (2.0 * $vmax * $vmax / ($sumA * $sumA)) * ((($a2 - $a1) / 3.0) + $a1);
        $dTwoRamps = 2.0 * $dRamp;

        // Case 1: reach vmax and cruise
        if ($distanceM >= $dTwoRamps) {
            $cruiseDist = $distanceM - $dTwoRamps;

            return (2.0 * $tRamp) + ($cruiseDist / $vmax);
        }

        // Case 2: never reach vmax (solve for half-trip time within ramp interval)
        $halfDist = $distanceM / 2.0;
        $tHalf = $this->solveRampTimeForDistance($halfDist, $a1, $a2, $tRamp);
        if ($tHalf === null) {
            return null;
        }

        return 2.0 * $tHalf;
    }

    /**
     * Distance during ramp phase for time t:
     * a(t) = a1 + (a2-a1)*(t/tRamp)
     * s(t) = 0.5*a1*t^2 + (a2-a1)*t^3/(6*tRamp)
     */
    private function rampDistance(float $t, float $a1, float $a2, float $tRamp): float
    {
        $delta = $a2 - $a1;

        return (0.5 * $a1 * $t * $t) + (($delta * $t * $t * $t) / (6.0 * $tRamp));
    }

    /**
     * Monotonic solve for t in [0, tRamp] such that rampDistance(t) ~= targetDistance.
     * Uses binary search (robust and fast enough for a formatter).
     */
    private function solveRampTimeForDistance(float $targetDistance, float $a1, float $a2, float $tRamp): ?float
    {
        if ($targetDistance < 0 || $tRamp <= 0) {
            return null;
        }

        $low = 0.0;
        $high = $tRamp;

        // If even full ramp doesn't cover target, something is inconsistent
        $maxDist = $this->rampDistance($tRamp, $a1, $a2, $tRamp);
        if ($targetDistance > $maxDist) {
            return null;
        }

        // Binary search
        for ($i = 0; $i < 60; $i++) {
            $mid = ($low + $high) / 2.0;
            $d = $this->rampDistance($mid, $a1, $a2, $tRamp);

            if ($d < $targetDistance) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return ($low + $high) / 2.0;
    }

    private function getQuantumFuelMicroUnitsPerSecond(): ?float
    {
        $basePath = 'Components/ItemResourceComponentParams/states/ItemResourceState[@name="Travelling"]/deltas/ItemResourceDeltaConsumption/consumption[@resource="QuantumFuel"]/resourceAmountPerSecond';
        $micro = $this->get($basePath.'/SMicroResourceUnit@microResourceUnits');

        if ($micro === null) {
            return null;
        }

        return (float) $micro;
    }

    private function formatDuration(?float $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        $s = (int) floor($seconds);
        if ($s < 0) {
            return null;
        }

        $hours = intdiv($s, 3600);
        $minutes = intdiv($s % 3600, 60);
        $secs = $s % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%d:%02d', $minutes, $secs);
    }
}
