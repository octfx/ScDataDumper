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

        foreach ($this->strategies as $strategy) {
            $supports = $strategy->supports($vehicle);
            if ($supports) {
                $result = $strategy->calculate($vehicle, $mass);
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

        $finalResult = $result ?? [];

        $finalResult['Tracks'] = $tracks;

        if ($wheelParams !== null) {
            $finalResult = array_merge($finalResult, $wheelParams);
        }

        if ($wheelAggregates !== null) {
            $finalResult = array_merge($finalResult, $wheelAggregates);
        }

        if ($agilityScores !== null) {
            $finalResult = array_merge($finalResult, $agilityScores);
        }

        if ($performanceMetrics !== null) {
            $finalResult = array_merge($finalResult, $performanceMetrics);
        }

        return $finalResult;
    }
}
