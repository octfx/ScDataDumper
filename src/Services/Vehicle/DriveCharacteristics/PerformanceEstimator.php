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

        $engineData = $this->extractEngineData($movementData);

        $result = [];

        if (! empty($speedMetrics)) {
            $result['Speed'] = $speedMetrics;
        }

        if ($engineData !== null) {
            $result['Engine'] = $engineData;
        }

        if (empty($result)) {
            return null;
        }

        return $result;
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
     *
     * Physics-based wheel speed: wWheelsMax (rad/s) × RimRadius (m) = linear velocity (m/s).
     * When available, authoritative Handling.Power.TopSpeed/ReverseSpeed are also extracted.
     *
     * @param  array<string, mixed>  $physicalWheeled  PhysicalWheeled movement data
     * @param  array<string, mixed>  $wheelAggregates  Wheel aggregates
     * @return array<string, mixed> Speed metrics
     */
    private function calculatePhysicalWheeledSpeed(array $physicalWheeled, array $wheelAggregates): array
    {
        $metrics = [
            'TopSpeedMs' => null,
            'TopSpeedKph' => null,
            'ReverseSpeedMs' => null,
            'ReverseSpeedKph' => null,
            'WheelMaxSpeedMs' => null,
            'WheelMaxSpeedKph' => null,
        ];

        if (isset($physicalWheeled['Handling']['Power'])) {
            $power = $physicalWheeled['Handling']['Power'];

            if (isset($power['TopSpeed']) && is_numeric($power['TopSpeed'])) {
                $topSpeedMs = (float) $power['TopSpeed'];
                $metrics['TopSpeedMs'] = $topSpeedMs;
                $metrics['TopSpeedKph'] = $topSpeedMs * self::M_TO_KPH_FACTOR;
            }

            if (isset($power['ReverseSpeed']) && is_numeric($power['ReverseSpeed'])) {
                $reverseSpeedMs = (float) $power['ReverseSpeed'];
                $metrics['ReverseSpeedMs'] = $reverseSpeedMs;
                $metrics['ReverseSpeedKph'] = $reverseSpeedMs * self::M_TO_KPH_FACTOR;
            }
        }

        $wWheelsMax = $physicalWheeled['PhysicsParams']['WWheelsMax'] ?? null;
        $rimRadius = $wheelAggregates['Wheels']['RimRadiusMeters'] ?? null;

        if (is_numeric($wWheelsMax) && is_numeric($rimRadius)) {
            $wheelMaxSpeedMs = (float) $wWheelsMax * (float) $rimRadius;
            $metrics['WheelMaxSpeedMs'] = $wheelMaxSpeedMs;
            $metrics['WheelMaxSpeedKph'] = $wheelMaxSpeedMs * self::M_TO_KPH_FACTOR;
        }

        // Derive reverse speed from gear ratios when no explicit Handling.Power.ReverseSpeed
        if ($metrics['ReverseSpeedMs'] === null) {
            $reverseRatio = $physicalWheeled['PhysicsParams']['Gears']['Reverse'] ?? null;
            $firstRatio = $physicalWheeled['PhysicsParams']['Gears']['First'] ?? null;
            if (is_numeric($reverseRatio) && is_numeric($firstRatio) && (float) $firstRatio > 0) {
                $baseSpeed = $metrics['TopSpeedMs'] ?? $metrics['WheelMaxSpeedMs'];
                if ($baseSpeed !== null) {
                    $reverseSpeedMs = (float) $baseSpeed * (float) $reverseRatio / ((float) $firstRatio + (float) $reverseRatio);
                    $metrics['ReverseSpeedMs'] = $reverseSpeedMs;
                    $metrics['ReverseSpeedKph'] = $reverseSpeedMs * self::M_TO_KPH_FACTOR;
                }
            }
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

    /**
     * Extract engine parameters from PhysicalWheeled movement data
     *
     * Only populated for PhysicalWheeled vehicles. Extracts brake torque, RPM,
     * torque scale, and gear information from the PhysicsParams section.
     *
     * @param  array<string, mixed>|null  $movementData  Movement data from strategies
     * @return array<string, mixed>|null Engine data or null if not a PhysicalWheeled vehicle
     */
    private function extractEngineData(?array $movementData): ?array
    {
        if ($movementData === null || ! isset($movementData['Movement']['PhysicalWheeled']['PhysicsParams'])) {
            return null;
        }

        $physicsParams = $movementData['Movement']['PhysicalWheeled']['PhysicsParams'];

        $engine = array_filter([
            'BrakeTorque' => $this->coerceNumeric($physicsParams['BrakeTorque'] ?? null),
            'RPMMax' => $this->coerceNumeric($physicsParams['Engine']['RPMmax'] ?? null),
            'RPMIdle' => $this->coerceNumeric($physicsParams['Engine']['RPMidle'] ?? null),
            'TorqueScale' => $this->coerceNumeric($physicsParams['Engine']['TorqueScale'] ?? null),
        ], static fn ($v) => $v !== null);

        $gears = $physicsParams['Gears'] ?? null;
        if (is_array($gears)) {
            $gearSection = $this->extractGears($gears);
            if (! empty($gearSection)) {
                $engine['Gears'] = $gearSection;
            }
        }

        return empty($engine) ? null : $engine;
    }

    /**
     * Extract gear information from Gears element
     *
     * Counts forward gears (first through sixth, etc.) and extracts reverse ratio.
     *
     * @param  array<string, mixed>  $gears  Gears data from PhysicsParams
     * @return array<string, mixed> Gear information
     */
    private function extractGears(array $gears): array
    {
        $forwardCount = 0;
        $reverseRatio = null;
        $firstRatio = null;

        $forwardGearNames = [
            'First', 'Second', 'Third', 'Fourth', 'Fifth', 'Sixth',
            'Seventh', 'Eighth', 'Ninth', 'Tenth',
        ];

        foreach ($forwardGearNames as $gearName) {
            if (isset($gears[$gearName])) {
                $forwardCount++;
            }
        }

        if (isset($gears['Reverse']) && is_numeric($gears['Reverse'])) {
            $reverseRatio = (float) $gears['Reverse'];
        }

        if (isset($gears['First']) && is_numeric($gears['First'])) {
            $firstRatio = (float) $gears['First'];
        }

        $result = [];
        if ($forwardCount > 0) {
            $result['ForwardCount'] = $forwardCount;
        }
        if ($reverseRatio !== null) {
            $result['ReverseRatio'] = $reverseRatio;
        }
        if ($firstRatio !== null) {
            $result['FirstRatio'] = $firstRatio;
        }

        return $result;
    }

    /**
     * Coerce a value to numeric (int or float), returning null if not possible
     */
    private function coerceNumeric(mixed $value): int|float|null
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            $float = (float) $value;

            return (float) ((int) $float) === $float ? (int) $float : $float;
        }

        return null;
    }
}
