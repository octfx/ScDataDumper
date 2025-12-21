<?php

namespace Octfx\ScDataDumper\Services\Vehicle\CargoGridStrategies;

use Illuminate\Support\Arr;
use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\ValueObjects\CargoGridResult;

/**
 * Add ResourceContainer-based cargo capacity
 *
 * Some vehicles have cargo capacity defined directly in ResourceContainer
 * components rather than as physical cargo grids. This strategy detects
 * and adds this capacity to the total.
 */
final class ResourceContainerCargoStrategy implements CargoGridStrategyInterface
{
    public function resolve(VehicleWrapper $vehicle, CargoGridResult $result): void
    {
        $resourceContainerCapacity = collect($vehicle->loadout)
            ->filter(fn ($x) => (
                isset($x['Item']['Components']['ResourceContainer']) &&
                ($x['Item']['Type'] ?? '') === 'Ship.Container.Cargo'
            ))
            ->sum(fn ($x) => Arr::get($x, 'Item.Components.ResourceContainer.capacity.SStandardCargoUnit.standardCargoUnits', 0));

        if ($resourceContainerCapacity > 0) {
            $result->addCapacity($resourceContainerCapacity);
        }
    }
}
