<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle\DriveCharacteristics;

/**
 * Calculate agility scores for ground vehicles from wheel aggregates
 *
 * This calculator computes normalized agility metrics based on wheel,
 * friction, and suspension characteristics.
 */
final readonly class GroundVehicleAgilityCalculator
{
    /**
     * Normalization factors for agility scores
     */
    private const float MAX_FRICTION = 8.0;

    private const float MAX_STIFFNESS = 2_300_000.0;

    private const float MAX_TORQUE_SCALE = 3.0;

    /**
     * Calculate agility scores from wheel aggregates
     *
     * @param  array<string, mixed>|null  $wheelAggregates  Aggregated wheel data from WheelAggregator
     * @return array<string, mixed>|null Agility scores or null if no data
     */
    public function calculate(?array $wheelAggregates): ?array
    {
        if (! $wheelAggregates) {
            return null;
        }

        // Extract required data
        $wheels = $wheelAggregates['Wheels'] ?? [];
        $friction = $wheelAggregates['Friction'] ?? [];
        $suspension = $wheelAggregates['Suspension'] ?? [];

        if (empty($wheels)) {
            return null;
        }

        return [
            'Agility' => [
                'HandlingScore' => $this->calculateHandlingScore($wheels),
                'GripScore' => $this->calculateGripScore($friction),
                'AccelerationScore' => $this->calculateAccelerationScore($wheels, $friction),
                'SuspensionCompliance' => $this->calculateSuspensionCompliance($suspension),
            ],
        ];
    }

    /**
     * Calculate handling score: ratio of steerable wheels to total wheels
     *
     * @param  array<string, mixed>  $wheels  Wheels aggregate data
     */
    private function calculateHandlingScore(array $wheels): float
    {
        $wheelCount = $wheels['Count'] ?? 0;
        $steeringCount = $wheels['SteeringCount'] ?? 0;

        if ($wheelCount === 0) {
            return 0.0;
        }

        return min($steeringCount / $wheelCount, 1.0);
    }

    /**
     * Calculate grip score: normalized by max friction of 8
     *
     * @param  array<string, mixed>  $friction  Friction aggregate data
     */
    private function calculateGripScore(array $friction): float
    {
        $maxFrictionAverage = $friction['MaxFrictionAverage'] ?? null;

        if ($maxFrictionAverage === null) {
            return 0.0;
        }

        return min($maxFrictionAverage / self::MAX_FRICTION, 1.0);
    }

    /**
     * Calculate acceleration score:
     * - If DrivingCount > 0: DrivingCount / Wheels.Count
     * - Else: TorqueScaleAverage / 3 (fallback for vehicles with no driving wheels)
     *
     * @param  array<string, mixed>  $wheels  Wheels aggregate data
     * @param  array<string, mixed>  $friction  Friction aggregate data (for potential future use)
     */
    private function calculateAccelerationScore(array $wheels, array $friction): float
    {
        $wheelCount = $wheels['Count'] ?? 0;
        $drivingCount = $wheels['DrivingCount'] ?? 0;
        $torqueScaleAverage = $wheels['TorqueScaleAverage'] ?? null;

        if ($wheelCount === 0) {
            return 0.0;
        }

        // If there are driving wheels, use the ratio
        if ($drivingCount > 0) {
            return min($drivingCount / $wheelCount, 1.0);
        }

        // Fallback: use torque scale average (e.g., for DRAK_Mule with DrivingCount=0)
        if ($torqueScaleAverage !== null) {
            return min($torqueScaleAverage / self::MAX_TORQUE_SCALE, 1.0);
        }

        return 0.0;
    }

    /**
     * Calculate suspension compliance: normalized by max stiffness of 2.3 million
     *
     * @param  array<string, mixed>  $suspension  Suspension aggregate data
     */
    private function calculateSuspensionCompliance(array $suspension): float
    {
        $stiffnessAverage = $suspension['StiffnessAverage'] ?? null;

        if ($stiffnessAverage === null) {
            return 0.0;
        }

        return min($stiffnessAverage / self::MAX_STIFFNESS, 1.0);
    }
}
