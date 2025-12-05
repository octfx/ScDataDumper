<?php

namespace Octfx\ScDataDumper\Services\Vehicle;

use Illuminate\Support\Collection;
use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\Vehicle\CargoGridStrategies\BaseClassCargoStrategy;
use Octfx\ScDataDumper\Services\Vehicle\CargoGridStrategies\ConventionBasedCargoStrategy;
use Octfx\ScDataDumper\Services\Vehicle\CargoGridStrategies\FallbackCargoStrategy;
use Octfx\ScDataDumper\Services\Vehicle\CargoGridStrategies\LoadoutCargoGridStrategy;
use Octfx\ScDataDumper\Services\Vehicle\CargoGridStrategies\PrefixBasedCargoStrategy;
use Octfx\ScDataDumper\Services\Vehicle\CargoGridStrategies\ResourceContainerCargoStrategy;
use Octfx\ScDataDumper\Services\Vehicle\CargoGridStrategies\VehicleComponentCargoStrategy;
use Octfx\ScDataDumper\ValueObjects\CargoGridResult;

/**
 * Resolves cargo grids for a vehicle using multiple fallback strategies
 *
 * This service orchestrates a series of cargo grid resolution strategies,
 * executing them in priority order until all cargo grids are found or
 * all strategies are exhausted.
 *
 * Strategy execution order:
 * 1. LoadoutCargoGridStrategy - Extract from vehicle loadout (primary)
 * 2. ResourceContainerCargoStrategy - ResourceContainer-based cargo
 * 3. ConventionBasedCargoStrategy - Convention-based class names
 * 4. PrefixBasedCargoStrategy - Vehicle prefix search
 * 5. BaseClassCargoStrategy - Base implementation class prefix
 * 6. VehicleComponentCargoStrategy - Direct vehicle component inventory
 * 7. FallbackCargoStrategy - Hardcoded cargo values (last resort)
 */
final class CargoGridResolver
{
    /** @var array<int, CargoGridStrategies\CargoGridStrategyInterface> */
    private array $strategies;

    public function __construct()
    {
        // executed in order
        $this->strategies = [
            new LoadoutCargoGridStrategy,
            new ResourceContainerCargoStrategy,
            new ConventionBasedCargoStrategy,
            new PrefixBasedCargoStrategy,
            new BaseClassCargoStrategy,
            new VehicleComponentCargoStrategy,
            new FallbackCargoStrategy,
        ];
    }

    /**
     * Resolve cargo grids for a vehicle
     *
     * Executes all strategies in sequence, allowing each to contribute
     * cargo grids and capacity to the result.
     *
     * @param  VehicleWrapper  $vehicle  The vehicle to resolve cargo grids for
     * @return CargoGridResult The resolved cargo grid data
     */
    public function resolveCargoGrids(VehicleWrapper $vehicle): CargoGridResult
    {
        $result = new CargoGridResult;

        foreach ($this->strategies as $strategy) {
            $strategy->resolve($vehicle, $result);

            if ($result->isSatisfied()) {
                break;
            }
        }

        $this->finalizeCargo($result);

        return $result;
    }

    /**
     * Finalize cargo grid collection
     *
     * Converts fallback containers to standardized grid format and merges
     * them with the existing grids collection.
     *
     * @param  CargoGridResult  $result  The result to finalize
     */
    private function finalizeCargo(CargoGridResult $result): void
    {
        if (empty($result->fallbackContainers)) {
            return;
        }

        $fallbackGrids = collect($result->fallbackContainers)
            ->filter()
            ->map(static fn ($container) => $container->toArray())
            ->filter()
            ->reject(function ($grid) use ($result) {
                return $result->grids->contains(
                    fn ($existing) => ($existing['uuid'] ?? null) === ($grid['uuid'] ?? null)
                );
            });

        $result->grids = $result->grids->merge($fallbackGrids);
    }

    /**
     * Calculate cargo grid size limits from a collection of cargo grids
     *
     * Finds the minimum and maximum cargo box sizes across all grids.
     *
     * @param  Collection  $cargoGrids  Collection of cargo grid arrays
     * @return array{MinSize: array|null, MaxSize: array|null}
     */
    public function calculateCargoGridSizeLimits(Collection $cargoGrids): array
    {
        $minVolumeGrid = $cargoGrids
            ->filter(fn ($grid) => isset($grid['minSize']['x'], $grid['minSize']['y'], $grid['minSize']['z']))
            ->sortBy(fn ($grid) => $grid['minSize']['x'] * $grid['minSize']['y'] * $grid['minSize']['z'])
            ->first();

        $maxVolumeGrid = $cargoGrids
            ->filter(fn ($grid) => isset($grid['maxSize']['x'], $grid['maxSize']['y'], $grid['maxSize']['z']))
            ->sortByDesc(fn ($grid) => $grid['maxSize']['x'] * $grid['maxSize']['y'] * $grid['maxSize']['z'])
            ->first();

        return [
            'MinSize' => $minVolumeGrid['minSize'] ?? null,
            'MaxSize' => $maxVolumeGrid['maxSize'] ?? null,
        ];
    }
}
