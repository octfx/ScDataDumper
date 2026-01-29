<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Services\Vehicle\DriveCharacteristics;

use Octfx\ScDataDumper\DocumentTypes\Vehicle;

/**
 * Derive speed metrics from MovementParams and wheel aggregates
 *
 * This estimator calculates various speed metrics from vehicle movement parameters
 * and wheel data, documenting the source of each value.
 */
final readonly class PerformanceEstimator
{
    private const M_TO_KPH_FACTOR = 3.6;

    /**
     * Estimate speed metrics from vehicle data
     *
     * @param  Vehicle|null  $vehicle  The vehicle document
     * @param  array<string, mixed>|null  $movementData  Movement data from strategies
     * @param  array<string, mixed>|null  $wheelAggregates  Wheel aggregates from WheelAggregator
     * @return array<string, mixed>|null Speed metrics or null if no data
     */
    public function estimate(?Vehicle $vehicle, ?array $movementData, ?array $wheelAggregates): ?array
    {
        if (! $vehicle) {
            return null;
        }

        $speedMetrics = $this->calculateSpeedMetrics($vehicle, $movementData, $wheelAggregates);

        if (empty($speedMetrics)) {
            return null;
        }

        return [
            'Speed' => $speedMetrics,
        ];
    }

    /**
     * Calculate speed metrics based on vehicle type
     *
     * @param  Vehicle  $vehicle  The vehicle document
     * @param  array<string, mixed>|null  $movementData  Movement data
     * @param  array<string, mixed>|null  $wheelAggregates  Wheel aggregates
     * @return array<string, mixed> Speed metrics
     */
    private function calculateSpeedMetrics(Vehicle $vehicle, ?array $movementData, ?array $wheelAggregates): array
    {
        $metrics = [
            'TopSpeedMs' => null,
            'TopSpeedKph' => null,
            'ReverseSpeedMs' => null,
            'ReverseSpeedKph' => null,
            'WheelMaxSpeedMs' => null,
            'WheelMaxSpeedKph' => null,
            'TrackMaxSpeedMs' => null,
            'TrackMaxSpeedKph' => null,
        ];

        // Check for ArcadeWheeled (DRAK_Mule)
        if (isset($movementData['Movement']['ArcadeWheeled'])) {
            $arcadeMetrics = $this->calculateArcadeWheeledSpeed($movementData['Movement']['ArcadeWheeled']);
            $metrics = array_merge($metrics, $arcadeMetrics);
        }

        // Check for PhysicalWheeled (ANVL_Ballista) - needs wheel aggregates
        if (isset($movementData['Movement']['PhysicalWheeled']) && $wheelAggregates !== null) {
            $physicalMetrics = $this->calculatePhysicalWheeledSpeed(
                $movementData['Movement']['PhysicalWheeled'],
                $wheelAggregates
            );
            $metrics = array_merge($metrics, $physicalMetrics);
        }

        // Check for TrackWheeled (TMBL_Nova)
        if (isset($movementData['Movement']['TrackWheeled'])) {
            $trackMetrics = $this->calculateTrackWheeledSpeed($movementData['Movement']['TrackWheeled']);
            $metrics = array_merge($metrics, $trackMetrics);
        }

        return $metrics;
    }

    /**
     * Calculate speed for ArcadeWheeled vehicles
     * Source: Movement.ArcadeWheeled.Handling.Power.TopSpeed (m/s)
     *
     * @param  array<string, mixed>  $arcadeWheeled  ArcadeWheeled movement data
     * @return array<string, mixed> Speed metrics
     */
    private function calculateArcadeWheeledSpeed(array $arcadeWheeled): array
    {
        $metrics = [
            'TopSpeedMs' => null,
            'TopSpeedKph' => null,
            'ReverseSpeedMs' => null,
            'ReverseSpeedKph' => null,
        ];

        // Extract from Handling.Power (source: Movement.ArcadeWheeled.Handling.Power)
        if (isset($arcadeWheeled['Handling']) && isset($arcadeWheeled['Handling']['Power'])) {
            $power = $arcadeWheeled['Handling']['Power'];

            // TopSpeed (in m/s from XML)
            if (isset($power['TopSpeed']) && is_numeric($power['TopSpeed'])) {
                $topSpeedMs = (float) $power['TopSpeed'];
                $metrics['TopSpeedMs'] = $topSpeedMs;
                $metrics['TopSpeedKph'] = $topSpeedMs * self::M_TO_KPH_FACTOR;
            }

            // ReverseSpeed (in m/s from XML)
            if (isset($power['ReverseSpeed']) && is_numeric($power['ReverseSpeed'])) {
                $reverseSpeedMs = (float) $power['ReverseSpeed'];
                $metrics['ReverseSpeedMs'] = $reverseSpeedMs;
                $metrics['ReverseSpeedKph'] = $reverseSpeedMs * self::M_TO_KPH_FACTOR;
            }
        }

        return $metrics;
    }

    /**
     * Calculate speed for PhysicalWheeled vehicles
     * Source: Movement.PhysicalWheeled.PhysicsParams.wWheelsMax and wheel RimRadius
     * Formula: WheelMaxSpeed = wWheelsMax * RimRadius * 2π (m/s)
     *
     * @param  array<string, mixed>  $physicalWheeled  PhysicalWheeled movement data
     * @param  array<string, mixed>  $wheelAggregates  Wheel aggregates
     * @return array<string, mixed> Speed metrics
     */
    private function calculatePhysicalWheeledSpeed(array $physicalWheeled, array $wheelAggregates): array
    {
        $metrics = [
            'WheelMaxSpeedMs' => null,
            'WheelMaxSpeedKph' => null,
        ];

        $wWheelsMax = $physicalWheeled['PhysicsParams']['WWheelsMax'] ?? null;

        $rimRadius = $wheelAggregates['Wheels']['RimRadiusMeters'] ?? null;

        // Calculate wheel max speed: wWheelsMax (rad/s) * RimRadius (m) * 2π = m/s
        if (is_numeric($wWheelsMax) && is_numeric($rimRadius)) {
            $wheelMaxSpeedMs = (float) $wWheelsMax * (float) $rimRadius * 2 * M_PI;
            $metrics['WheelMaxSpeedMs'] = $wheelMaxSpeedMs;
            $metrics['WheelMaxSpeedKph'] = $wheelMaxSpeedMs * self::M_TO_KPH_FACTOR;
        }

        return $metrics;
    }

    /**
     * Calculate speed for TrackWheeled vehicles
     * Source: Movement.TrackWheeled.MaxSpeed (m/s)
     *
     * @param  array<string, mixed>  $trackWheeled  TrackWheeled movement data
     * @return array<string, mixed> Speed metrics
     */
    private function calculateTrackWheeledSpeed(array $trackWheeled): array
    {
        $metrics = [
            'TrackMaxSpeedMs' => null,
            'TrackMaxSpeedKph' => null,
        ];

        // MaxSpeed from TrackWheeled (source: Movement.TrackWheeled.MaxSpeed)
        if (isset($trackWheeled['MaxSpeed']) && is_numeric($trackWheeled['MaxSpeed'])) {
            $trackMaxSpeedMs = (float) $trackWheeled['MaxSpeed'];
            $metrics['TrackMaxSpeedMs'] = $trackMaxSpeedMs;
            $metrics['TrackMaxSpeedKph'] = $trackMaxSpeedMs * self::M_TO_KPH_FACTOR;
        }

        return $metrics;
    }
}
