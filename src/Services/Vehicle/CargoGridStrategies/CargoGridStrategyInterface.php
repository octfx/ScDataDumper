<?php

namespace Octfx\ScDataDumper\Services\Vehicle\CargoGridStrategies;

use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\ValueObjects\CargoGridResult;

/**
 * Strategy interface for cargo grid resolution
 *
 * Each strategy implements a different method of discovering cargo grids
 * for a vehicle. Strategies are executed in sequence until all cargo
 * grids are found or all strategies are exhausted.
 */
interface CargoGridStrategyInterface
{
    /**
     * Attempt to resolve cargo grids using this strategy
     *
     * @param  VehicleWrapper  $vehicle  The vehicle to resolve cargo grids for
     * @param  CargoGridResult  $result  The result object to populate
     */
    public function resolve(VehicleWrapper $vehicle, CargoGridResult $result): void;
}
