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

    public function __construct()
    {
        $this->strategies = [
            new ArcadeWheeledCalculator,
            new PhysicalWheeledCalculator,
        ];
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

        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($vehicle)) {
                return $strategy->calculate($vehicle, $mass);
            }
        }

        return null;
    }
}
