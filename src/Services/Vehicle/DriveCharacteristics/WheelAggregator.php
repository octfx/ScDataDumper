<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle\DriveCharacteristics;

/**
 * Aggregate wheel metrics from RawWheelData into structured DriveCharacteristics
 *
 * This aggregator takes the raw wheel data extracted by WheelParametersExtractor
 * and computes aggregated metrics for Wheels, Friction, and Suspension.
 */
final readonly class WheelAggregator
{
    /**
     * Aggregate wheel data into DriveCharacteristics structure
     *
     * @param  array<string, mixed>|null  $rawWheelData  Raw wheel data from WheelParametersExtractor
     * @return array<string, mixed>|null Aggregated characteristics or null if no data
     */
    public function aggregate(?array $rawWheelData): ?array
    {
        if (! $rawWheelData || ! isset($rawWheelData['RawWheelData'])) {
            return null;
        }

        $wheels = $rawWheelData['RawWheelData'];
        if (empty($wheels)) {
            return null;
        }

        return [
            'Wheels' => $this->aggregateWheels($wheels),
            'Friction' => $this->aggregateFriction($wheels),
            'Suspension' => $this->aggregateSuspension($wheels),
        ];
    }

    /**
     * Aggregate wheel metrics
     *
     * @param  array<int, array<string, mixed>>  $wheels  Array of wheel data
     * @return array<string, mixed> Wheels aggregates
     */
    private function aggregateWheels(array $wheels): array
    {
        $count = count($wheels);
        $drivingCount = 0;
        $steeringCount = 0;

        $rimRadii = [];
        $torqueScales = [];

        foreach ($wheels as $wheel) {
            // Count driving wheels
            if (isset($wheel['Driving']) && $wheel['Driving'] === 1) {
                $drivingCount++;
            }

            // Count steering wheels
            if (isset($wheel['CanSteer']) && $wheel['CanSteer'] === true) {
                $steeringCount++;
            }

            // Collect rim radii for averaging
            if (isset($wheel['RimRadius']) && is_numeric($wheel['RimRadius'])) {
                $rimRadii[] = (float) $wheel['RimRadius'];
            }

            // Collect torque scales for averaging
            if (isset($wheel['TorqueScale']) && is_numeric($wheel['TorqueScale'])) {
                $torqueScales[] = (float) $wheel['TorqueScale'];
            }
        }

        return [
            'Count' => $count,
            'DrivingCount' => $drivingCount,
            'SteeringCount' => $steeringCount,
            'RimRadiusMeters' => $this->average($rimRadii),
            'TorqueScaleAverage' => $this->average($torqueScales),
        ];
    }

    /**
     * Aggregate friction metrics
     *
     * @param  array<int, array<string, mixed>>  $wheels  Array of wheel data
     * @return array<string, mixed>|null Friction aggregates or null if no data
     */
    private function aggregateFriction(array $wheels): ?array
    {
        $maxFrictions = [];
        $minFrictions = [];

        foreach ($wheels as $wheel) {
            // Collect max friction values
            if (isset($wheel['MaxFriction']) && is_numeric($wheel['MaxFriction'])) {
                $maxFrictions[] = (float) $wheel['MaxFriction'];
            }

            // Collect min friction values
            if (isset($wheel['MinFriction']) && is_numeric($wheel['MinFriction'])) {
                $minFrictions[] = (float) $wheel['MinFriction'];
            }
        }

        $result = array_filter([
            'MaxFrictionAverage' => $this->average($maxFrictions),
            'MinFrictionAverage' => $this->average($minFrictions),
        ], fn ($v) => $v !== null);

        return empty($result) ? null : $result;
    }

    /**
     * Aggregate suspension metrics
     *
     * @param  array<int, array<string, mixed>>  $wheels  Array of wheel data
     * @return array<string, mixed>|null Suspension aggregates or null if no data
     */
    private function aggregateSuspension(array $wheels): ?array
    {
        $stiffnessValues = [];
        $dampingValues = [];
        $suspensionLengthValues = [];
        $maxExtensionValues = [];

        foreach ($wheels as $wheel) {
            // Collect stiffness values
            if (isset($wheel['Stiffness']) && is_numeric($wheel['Stiffness'])) {
                $stiffnessValues[] = (float) $wheel['Stiffness'];
            }

            // Collect damping values
            if (isset($wheel['Damping']) && is_numeric($wheel['Damping'])) {
                $dampingValues[] = (float) $wheel['Damping'];
            }

            // Collect suspension length values
            if (isset($wheel['SuspensionLength']) && is_numeric($wheel['SuspensionLength'])) {
                $suspensionLengthValues[] = (float) $wheel['SuspensionLength'];
            }

            // Collect max extension values
            if (isset($wheel['MaxExtension']) && is_numeric($wheel['MaxExtension'])) {
                $maxExtensionValues[] = (float) $wheel['MaxExtension'];
            }
        }

        $result = array_filter([
            'StiffnessAverage' => $this->average($stiffnessValues),
            'DampingAverage' => $this->average($dampingValues),
            'SuspensionLengthMeters' => $this->average($suspensionLengthValues),
            'MaxExtensionMeters' => $this->average($maxExtensionValues),
        ], static fn ($v) => $v !== null);

        return empty($result) ? null : $result;
    }

    /**
     * Calculate arithmetic mean of values, excluding nulls
     *
     * @param  array<float>  $values  Array of numeric values
     * @return float|null Average or null if no valid values
     */
    private function average(array $values): ?float
    {
        $filteredValues = array_filter($values, static fn ($v) => is_numeric($v));

        if (empty($filteredValues)) {
            return null;
        }

        return array_sum($filteredValues) / count($filteredValues);
    }
}
