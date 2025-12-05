<?php

namespace Octfx\ScDataDumper\Services\Vehicle\CargoGridStrategies;

use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\ValueObjects\CargoGridResult;

/**
 * Hardcoded cargo capacity fallback
 *
 * As an absolute last resort, uses cargo capacity values hardcoded in the
 * vehicle XML data. This is used when no cargo grids can be discovered
 * through any other method.
 */
final class FallbackCargoStrategy implements CargoGridStrategyInterface
{
    public function resolve(VehicleWrapper $vehicle, CargoGridResult $result): void
    {
        if ($result->totalCapacity > 0) {
            return;
        }

        $cargoFallback = $vehicle->vehicle?->get('Cargo')
            ?? $vehicle->entity->get('Components/VehicleComponentParams@cargo');

        if ($cargoFallback !== null && is_numeric($cargoFallback)) {
            $result->totalCapacity = (float) $cargoFallback;
        }
    }
}
