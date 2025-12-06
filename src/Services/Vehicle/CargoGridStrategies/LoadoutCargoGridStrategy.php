<?php

namespace Octfx\ScDataDumper\Services\Vehicle\CargoGridStrategies;

use Illuminate\Support\Collection;
use Octfx\ScDataDumper\Helper\Arr;
use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\ValueObjects\CargoGridResult;

/**
 * Extract cargo grids from the vehicle loadout
 *
 * This is the primary strategy - it looks for CargoGrid type items in the
 * vehicle's loadout entries and processes them into standardised grid data.
 */
final class LoadoutCargoGridStrategy implements CargoGridStrategyInterface
{
    public function resolve(VehicleWrapper $vehicle, CargoGridResult $result): void
    {
        $cargoGrids = collect($vehicle->loadout)
            ->flatMap(function ($entry) {
                return $this->extractCargoGrids($entry);
            })
            ->filter(fn ($item) => ! $this->isTemplateGrid($item));

        $capacity = $cargoGrids->sum(function ($item) {
            $dimX = Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.interiorDimensions.x', 0);
            $dimY = Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.interiorDimensions.y', 0);
            $dimZ = Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.interiorDimensions.z', 0);

            return ($dimX * $dimY * $dimZ) / M_TO_SCU_UNIT;
        });

        $result->addCapacity($capacity);

        $inventoryContainerService = ServiceFactory::getInventoryContainerService();

        $standardisedGrids = $cargoGrids
            ->map(function ($item) use ($inventoryContainerService) {
                $containerRef = Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.containerParams')
                    ?? Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.__ref')
                    ?? ($item['__ref'] ?? null);

                // Prefer dedicated InventoryContainer documents when available
                $container = $inventoryContainerService->getByReference($containerRef);

                if ($container === null) {
                    $className = $item['className'] ?? $item['ClassName'] ?? null;
                    if ($className !== null) {
                        if (str_ends_with(strtolower($className), '_template')) {
                            return null;
                        }
                        $container = $inventoryContainerService->getByClassName($className);
                    }
                }

                if ($container !== null) {
                    if (str_ends_with(strtolower($container->getClassName()), '_template')) {
                        return null;
                    }
                    $dimensions = $container->getInteriorDimensions();

                    return [
                        'uuid' => $container->getUuid(),
                        'class' => $container->getClassName(),
                        'SCU' => $container->getSCU(),
                        'capacity' => $container->getCapacityValue(),
                        'capacity_name' => $container->getCapacityName(),
                        'x' => $dimensions['x'] ?? null,
                        'y' => $dimensions['y'] ?? null,
                        'z' => $dimensions['z'] ?? null,
                        'MinSize' => $container->getMinPermittedItemSize(),
                        'MaxSize' => $container->getMaxPermittedItemSize(),
                        'IsOpenContainer' => $container->isOpenContainer(),
                        'IsExternalContainer' => $container->isExternalContainer(),
                        'IsClosedContainer' => $container->isClosedContainer(),
                    ];
                }

                // Fallback to inline component data if no dedicated container document exists
                $dimX = Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.interiorDimensions.x');
                $dimY = Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.interiorDimensions.y');
                $dimZ = Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.interiorDimensions.z');

                if ($dimX === null || $dimY === null || $dimZ === null) {
                    return null;
                }

                $className = $item['className'] ?? $item['ClassName'] ?? '';
                if (str_ends_with(strtolower($className), '_template')) {
                    return null;
                }

                $scu = ($dimX * $dimY * $dimZ) / M_TO_SCU_UNIT;

                return [
                    'uuid' => $containerRef,
                    'class' => $item['className'] ?? $item['ClassName'] ?? null,
                    'SCU' => $scu,
                    'capacity' => $scu,
                    'capacity_name' => 'SCU',
                    'x' => $dimX,
                    'y' => $dimY,
                    'z' => $dimZ,
                    'MinSize' => Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.inventoryType.InventoryOpenContainerType.minPermittedItemSize'),
                    'MaxSize' => Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.inventoryType.InventoryOpenContainerType.maxPermittedItemSize'),
                    'IsOpenContainer' => true,
                    'IsExternalContainer' => (bool) Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.inventoryType.InventoryOpenContainerType.isExternalContainer'),
                    'IsClosedContainer' => false,
                ];
            })
            ->filter(fn ($x) => $x !== null);

        // Store standardised grids and track UUIDs
        $result->grids = $result->grids->merge($standardisedGrids);
        $result->existingGridUuids = $result->grids->pluck('uuid')->filter()->all();

        // Calculate expected cargo grid slots
        $expectedSlots = $this->countCargoGridPorts($vehicle->loadout);
        $result->setExpectedSlots($expectedSlots);
    }

    /**
     * Recursively extract cargo grids from loadout entries
     */
    private function extractCargoGrids(array $loadout): Collection
    {
        $grids = collect();

        if (
            Arr::get($loadout, 'Item.Components.SAttachableComponentParams.AttachDef.Type') === 'CargoGrid' &&
            isset($loadout['Item']['Components']['SCItemInventoryContainerComponentParams'])
        ) {
            $grids->push($loadout['Item']);
        }

        if (! empty($loadout['entries']) && is_array($loadout['entries'])) {
            foreach ($loadout['entries'] as $entry) {
                $grids = $grids->merge($this->extractCargoGrids($entry));
            }
        }

        $manualEntries = Arr::get($loadout, 'Item.Components.SEntityComponentDefaultLoadoutParams.loadout.SItemPortLoadoutManualParams.entries', []);
        foreach ($manualEntries as $entry) {
            if (isset($entry['InstalledItem'])) {
                $grids = $grids->merge($this->extractCargoGrids(['Item' => $entry['InstalledItem']]));
            }

            if (! empty($entry['entries']) && is_array($entry['entries'])) {
                foreach ($entry['entries'] as $subEntry) {
                    $grids = $grids->merge($this->extractCargoGrids($subEntry));
                }
            }
        }

        return $grids;
    }

    /**
     * Count item ports in the loadout whose names contain "cargogrid"
     */
    private function countCargoGridPorts(array $entries): int
    {
        $count = 0;

        $scanPorts = static function (array $ports) use (&$scanPorts, &$count): void {
            foreach ($ports as $port) {
                $name = strtolower($port['Name'] ?? '');
                $type = strtolower(Arr::get($port, 'Types.SItemPortDefTypes.Type', ''));

                if (($name !== '' && preg_match('/cargo[ _-]?grid/', $name)) || $type === 'cargogrid') {
                    $count++;
                }

                if (! empty($port['Ports']) && is_array($port['Ports'])) {
                    $scanPorts($port['Ports']);
                }
            }
        };

        $walker = static function (array $items) use (&$walker, &$scanPorts, &$count): void {
            foreach ($items as $entry) {
                $portName = strtolower($entry['portName'] ?? '');
                if ($portName !== '' && preg_match('/cargo[ _-]?grid/', $portName)) {
                    $count++;
                }

                $scanPorts(Arr::get($entry, 'Item.Components.SItemPortContainerComponentParams.Ports', []));

                if (! empty($entry['entries']) && is_array($entry['entries'])) {
                    $walker($entry['entries']);
                }

                $manualEntries = Arr::get($entry, 'Item.Components.SEntityComponentDefaultLoadoutParams.loadout.SItemPortLoadoutManualParams.entries', []);
                if (! empty($manualEntries)) {
                    $walker($manualEntries);
                }
            }
        };

        $walker($entries);

        return $count;
    }

    /**
     * Detect template/placeholder cargo grids that should be ignored.
     */
    private function isTemplateGrid(array $item): bool
    {
        $className = strtolower($item['className'] ?? $item['ClassName'] ?? '');
        if ($className !== '' && str_ends_with($className, '_template')) {
            return true;
        }

        $path = strtolower($item['__path'] ?? '');
        return $path !== '' && str_ends_with($path, '_cargogrid_template.xml');
    }
}
