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
 * 2. ConventionBasedCargoStrategy - Convention-based class names
 * 3. PrefixBasedCargoStrategy - Vehicle prefix search
 * 4. BaseClassCargoStrategy - Base implementation class prefix
 * 5. VehicleComponentCargoStrategy - Direct vehicle component inventory
 * 6. FallbackCargoStrategy - Hardcoded cargo values (last resort)
 * 7. ResourceContainerCargoStrategy - Ore pod capacity (always runs, independent)
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

        // Always run - ore pods are independent of cargo grids
        (new ResourceContainerCargoStrategy)->resolve($vehicle, $result);

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
        // Producers differ in casing: strategy grids use 'MinSize' with lowercase
        // sub-keys, fallback container grids are fully PascalCase.
        $size = static function (array $grid, string $key): ?array {
            foreach ([$key, strtolower($key)] as $k) {
                $value = $grid[$k] ?? null;
                if (is_array($value) && isset($value['x'], $value['y'], $value['z'])) {
                    return ['x' => $value['x'], 'y' => $value['y'], 'z' => $value['z']];
                }

                if (is_array($value) && isset($value['X'], $value['Y'], $value['Z'])) {
                    return ['x' => $value['X'], 'y' => $value['Y'], 'z' => $value['Z']];
                }
            }

            return null;
        };

        $minVolumeGrid = $cargoGrids
            ->map(fn ($grid) => ['grid' => $grid, 'size' => $size((array) $grid, 'MinSize')])
            ->filter(fn ($entry) => $entry['size'] !== null)
            ->sortBy(fn ($entry) => $entry['size']['x'] * $entry['size']['y'] * $entry['size']['z'])
            ->first()['size'] ?? null;

        $maxVolumeGrid = $cargoGrids
            ->map(fn ($grid) => ['grid' => $grid, 'size' => $size((array) $grid, 'MaxSize')])
            ->filter(fn ($entry) => $entry['size'] !== null)
            ->sortByDesc(fn ($entry) => $entry['size']['x'] * $entry['size']['y'] * $entry['size']['z'])
            ->first()['size'] ?? null;

        return [
            'MinSize' => $minVolumeGrid,
            'MaxSize' => $maxVolumeGrid,
        ];
    }
}
