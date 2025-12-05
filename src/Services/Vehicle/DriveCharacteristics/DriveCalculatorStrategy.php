<?php

namespace Octfx\ScDataDumper\Services\Vehicle\DriveCharacteristics;

use Octfx\ScDataDumper\DocumentTypes\Vehicle;

interface DriveCalculatorStrategy
{
    /**
     * Check if this calculator supports the given vehicle
     *
     * @param  Vehicle|null  $vehicle  The vehicle to check
     * @return bool True if this strategy can handle the vehicle
     */
    public function supports(?Vehicle $vehicle): bool;

    /**
     * Calculate drive characteristics
     *
     * @param  Vehicle  $vehicle  The vehicle to calculate for
     * @param  float  $mass  Vehicle mass in kg
     * @return array Drive characteristics data
     */
    public function calculate(Vehicle $vehicle, float $mass): array;
}
