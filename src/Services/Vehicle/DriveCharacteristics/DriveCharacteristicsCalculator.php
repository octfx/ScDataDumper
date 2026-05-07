<?php

namespace Octfx\ScDataDumper\Services\Vehicle\DriveCharacteristics;

use Octfx\ScDataDumper\DocumentTypes\Vehicle;

/**
 * Calculate drive characteristics using appropriate strategy
 */
final class DriveCharacteristicsCalculator
{
    /** @var DriveCalculatorStrategy[] */
    private array $strategies;

    private readonly TrackDetector $trackDetector;

    private readonly WheelParametersExtractor $wheelParametersExtractor;

    private readonly WheelAggregator $wheelAggregator;

    private readonly PerformanceEstimator $performanceEstimator;

    private readonly GroundVehicleAgilityCalculator $agilityCalculator;

    public function __construct()
    {
        $this->strategies = [
            new ArcadeWheeledCalculator,
            new PhysicalWheeledCalculator,
            new TrackWheeledCalculator,
        ];
        $this->trackDetector = new TrackDetector;
        $this->wheelParametersExtractor = new WheelParametersExtractor;
        $this->wheelAggregator = new WheelAggregator;
        $this->performanceEstimator = new PerformanceEstimator;
        $this->agilityCalculator = new GroundVehicleAgilityCalculator;
    }

    /**
     * Calculate drive characteristics for the vehicle
     *
     * @param  Vehicle|null  $vehicle  The vehicle to calculate for
     * @param  float  $mass  Vehicle mass
     * @return array|null Drive characteristics or null if no strategy supports the vehicle
     */
    public function calculate(?Vehicle $vehicle, float $mass): ?array
    {
        if (! $vehicle) {
            return null;
        }

        $result = null;
        $matchedStrategy = null;

        foreach ($this->strategies as $strategy) {
            $supports = $strategy->supports($vehicle);
            if ($supports) {
                $result = $strategy->calculate($vehicle, $mass);
                $matchedStrategy = $strategy;
                break;
            }
        }

        $tracks = $this->trackDetector->detect($vehicle);

        $wheelParams = $this->wheelParametersExtractor->extract($vehicle);

        $wheelAggregates = $this->wheelAggregator->aggregate($wheelParams);

        $agilityScores = $this->agilityCalculator->calculate($wheelAggregates);

        $performanceMetrics = $this->performanceEstimator->estimate($vehicle, $result, $wheelAggregates);

        if ($result === null && $tracks['IsTracked'] === false && $wheelParams === null) {
            return null;
        }

        $finalResult = [];

        $finalResult['Type'] = $this->resolveVehicleType($matchedStrategy);

        $finalResult['IsTracked'] = $tracks['IsTracked'];

        if ($performanceMetrics !== null && isset($performanceMetrics['Speed'])) {
            $finalResult['Speed'] = $performanceMetrics['Speed'];
        }

        if ($wheelAggregates !== null) {
            $finalResult['Wheels'] = $this->buildWheelsSection(
                $wheelAggregates,
                $wheelParams
            );
        }

        if ($performanceMetrics !== null && isset($performanceMetrics['Engine'])) {
            $finalResult['Engine'] = $performanceMetrics['Engine'];
        }

        if ($agilityScores !== null && isset($agilityScores['Agility'])) {
            $finalResult['Agility'] = $agilityScores['Agility'];
        }

        return $finalResult;
    }

    /**
     * Resolve the vehicle type string from the matched strategy
     *
     * @param  DriveCalculatorStrategy|null  $strategy  The matched strategy
     * @return string Vehicle type: "PhysicalWheeled", "ArcadeWheeled", "TrackWheeled", or "Unknown"
     */
    private function resolveVehicleType(?DriveCalculatorStrategy $strategy): string
    {
        return match (true) {
            $strategy instanceof PhysicalWheeledCalculator => 'PhysicalWheeled',
            $strategy instanceof ArcadeWheeledCalculator => 'ArcadeWheeled',
            $strategy instanceof TrackWheeledCalculator => 'TrackWheeled',
            default => 'Unknown',
        };
    }

    /**
     * Build the consolidated Wheels section with nested Friction and Suspension
     *
     * Removes TorqueScaleAverage from Wheels output (it's an engine param),
     * adds DriveType, and nests Friction/Suspension sections inside.
     *
     * @param  array<string, mixed>  $wheelAggregates  Aggregated wheel data
     * @param  array<string, mixed>|null  $wheelParams  Raw wheel params (for DriveType detection)
     * @return array<string, mixed> Consolidated Wheels section
     */
    private function buildWheelsSection(array $wheelAggregates, ?array $wheelParams): array
    {
        $wheels = $wheelAggregates['Wheels'] ?? [];

        unset($wheels['TorqueScaleAverage']);

        $rawWheelData = $wheelParams['RawWheelData'] ?? null;
        $wheels['DriveType'] = $this->detectDriveType($rawWheelData);

        if (isset($wheelAggregates['Friction'])) {
            $friction = [];
            if (isset($wheelAggregates['Friction']['MaxFrictionAverage'])) {
                $friction['MaxAverage'] = $wheelAggregates['Friction']['MaxFrictionAverage'];
            }
            if (isset($wheelAggregates['Friction']['MinFrictionAverage'])) {
                $friction['MinAverage'] = $wheelAggregates['Friction']['MinFrictionAverage'];
            }
            if (! empty($friction)) {
                $wheels['Friction'] = $friction;
            }
        }

        if (isset($wheelAggregates['Suspension'])) {
            $suspension = [];
            if (isset($wheelAggregates['Suspension']['StiffnessAverage'])) {
                $suspension['StiffnessAverage'] = $wheelAggregates['Suspension']['StiffnessAverage'];
            }
            if (isset($wheelAggregates['Suspension']['DampingAverage'])) {
                $suspension['DampingAverage'] = $wheelAggregates['Suspension']['DampingAverage'];
            }
            if (isset($wheelAggregates['Suspension']['SuspensionLengthMeters'])) {
                $suspension['LengthMeters'] = $wheelAggregates['Suspension']['SuspensionLengthMeters'];
            }
            if (isset($wheelAggregates['Suspension']['MaxExtensionMeters'])) {
                $suspension['MaxExtensionMeters'] = $wheelAggregates['Suspension']['MaxExtensionMeters'];
            }
            if (! empty($suspension)) {
                $wheels['Suspension'] = $suspension;
            }
        }

        return $wheels;
    }

    /**
     * Detect drive type from raw wheel data
     *
     * Logic:
     * - All wheels driving -> AWD
     * - Only front-axle (steering) wheels driving -> FWD
     * - Only rear-axle (non-steering) wheels driving -> RWD
     * - Some front + some rear driving -> 4WD
     * - No wheels driving -> Unknown (e.g. ArcadeWheeled Mule)
     *
     * @param  array<int, array<string, mixed>>|null  $rawWheelData  Per-wheel data from WheelParametersExtractor
     * @return string Drive type: "AWD", "FWD", "RWD", "4WD", or "Unknown"
     */
    private function detectDriveType(?array $rawWheelData): string
    {
        if ($rawWheelData === null || empty($rawWheelData)) {
            return 'Unknown';
        }

        $frontDriving = 0;
        $rearDriving = 0;
        $totalDriving = 0;

        foreach ($rawWheelData as $wheel) {
            $isDriving = isset($wheel['Driving']) && $wheel['Driving'] === 1;
            $canSteer = isset($wheel['CanSteer']) && $wheel['CanSteer'] === true;

            if ($isDriving) {
                $totalDriving++;
                if ($canSteer) {
                    $frontDriving++;
                } else {
                    $rearDriving++;
                }
            }
        }

        if ($totalDriving === 0) {
            return 'Unknown';
        }

        $totalWheels = count($rawWheelData);

        // All wheels driving: AWD
        if ($totalDriving === $totalWheels) {
            return 'AWD';
        }

        // Only front (steering) wheels driving: FWD
        if ($frontDriving > 0 && $rearDriving === 0) {
            return 'FWD';
        }

        // Only rear (non-steering) wheels driving: RWD
        if ($rearDriving > 0 && $frontDriving === 0) {
            return 'RWD';
        }

        // Mixed front + rear: 4WD
        return '4WD';
    }
}
