<?php

namespace Octfx\ScDataDumper\Services\Vehicle;

/**
 * Interface for vehicle data calculators
 *
 * All calculators implement this interface to enable orchestrated execution
 * with priority-based ordering and conditional calculation.
 */
interface VehicleDataCalculator
{
    /**
     * Check if calculator can run with given context
     *
     * @param  VehicleDataContext  $context  Calculation context with input data
     * @return bool True if calculator should run, false to skip
     */
    public function canCalculate(VehicleDataContext $context): bool;

    /**
     * Calculate and return results
     *
     * @param  VehicleDataContext  $context  Calculation context with input data
     * @return array Associative array of calculated data
     */
    public function calculate(VehicleDataContext $context): array;

    /**
     * Get calculator priority (lower = runs first)
     *
     * Priority levels:
     * - 20: Foundation calculators (Propulsion, Quantum)
     * - 30: Dependent calculators (FlightCharacteristics needs Propulsion output)
     * - 40: Independent calculators (Emission, Power, Resource, Health, Weapon)
     *
     * @return int Priority value
     */
    public function getPriority(): int;
}
