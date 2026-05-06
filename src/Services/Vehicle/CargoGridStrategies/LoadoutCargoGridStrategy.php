<?php

namespace Octfx\ScDataDumper\Services\Vehicle\CargoGridStrategies;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\ValueObjects\CargoGridResult;
use Octfx\ScDataDumper\ValueObjects\ScuCalculator;

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

        $inventoryContainerService = ServiceFactory::getInventoryContainerService();

        $resolvedCargoGrids = $cargoGrids
            ->map(function ($item) use ($inventoryContainerService) {
                $inlineScu = $this->calculateInlineGridScu($item);
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
                    $dimensions = $container->getInteriorDimensions();

                    $grid = [
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

                    return [
                        // Inline geometry is the authoritative cargo total when present.
                        'effective_scu' => $inlineScu ?? (float) ($grid['SCU'] ?? 0),
                        'grid' => $grid,
                    ];
                }

                // Fallback to inline component data if no dedicated container document exists
                $dimX = Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.interiorDimensions.x');
                $dimY = Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.interiorDimensions.y');
                $dimZ = Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.interiorDimensions.z');

                if ($dimX === null || $dimY === null || $dimZ === null) {
                    return [
                        'effective_scu' => 0.0,
                        'grid' => null,
                    ];
                }

                $className = $item['className'] ?? $item['ClassName'] ?? '';
                if (str_ends_with(strtolower($className), '_template')) {
                    return [
                        'effective_scu' => 0.0,
                        'grid' => null,
                    ];
                }

                $scu = ($dimX * $dimY * $dimZ) / ScuCalculator::M_TO_SCU_UNIT;

                return [
                    'effective_scu' => $inlineScu ?? $scu,
                    'grid' => [
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
                    ],
                ];
            })
            ->values();

        $result->addCapacity((float) $resolvedCargoGrids->sum(
            static fn (array $entry): float => (float) ($entry['effective_scu'] ?? 0)
        ));
        $standardisedGrids = $resolvedCargoGrids
            ->pluck('grid')
            ->filter(fn ($grid) => $grid !== null)
            ->values();

        // Store standardised grids and track UUIDs
        $result->grids = $result->grids->merge($standardisedGrids);
        $result->existingGridUuids = $result->grids->pluck('uuid')->filter()->all();

        // Track loadout-resolved grid class names so prefix/base strategies can detect
        // sibling variant grids for vehicles without a variant suffix (2-part class names).
        $result->loadoutGridClassNames = $standardisedGrids
            ->pluck('class')
            ->filter()
            ->map(static fn ($class) => strtolower($class))
            ->all();

        // Mark that the loadout strategy found grids so fallback strategies
        // can apply variant-aware filtering to avoid over-counting.
        if ($standardisedGrids->isNotEmpty()) {
            $result->markLoadoutFoundGrids();
        }

        // Keep expected slot estimation broad so fallback strategies can fill missing grids,
        // but avoid double-counting the same hardpoint while scanning.
        $result->setExpectedSlots($this->countCargoGridPorts($vehicle->loadout));

        // Detect cargo infrastructure so fallback strategies know whether the vehicle
        // has any cargo capability at all. When no infrastructure exists, prefix/base
        // strategies should not add grids from sibling variants.
        if ($this->hasCargoInfrastructure($vehicle->loadout)) {
            $result->markHasCargoInfrastructure();
        }
    }

    /**
     * Recursively extract cargo grids from loadout entries
     */
    private function extractCargoGrids(array $loadout): Collection
    {
        $grids = collect();

        if (
            Arr::get($loadout, 'ItemRaw.Components.SAttachableComponentParams.AttachDef.Type') === 'CargoGrid' &&
            isset($loadout['ItemRaw']['Components']['SCItemInventoryContainerComponentParams'])
        ) {
            $grids->push($loadout['ItemRaw']);
        }

        if (! empty($loadout['entries']) && is_array($loadout['entries'])) {
            foreach ($loadout['entries'] as $entry) {
                $grids = $grids->merge($this->extractCargoGrids($entry));
            }
        }

        $manualEntries = Arr::get($loadout, 'ItemRaw.Components.SEntityComponentDefaultLoadoutParams.loadout.SItemPortLoadoutManualParams.entries', []);
        foreach ($manualEntries as $entry) {
            if (
                isset($entry['InstalledItem']) &&
                ! $this->hasEquivalentItemInEntries($loadout['entries'] ?? [], $entry['InstalledItem'])
            ) {
                $grids = $grids->merge($this->extractCargoGrids(['ItemRaw' => $entry['InstalledItem']]));
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
     * Count candidate cargo-grid ports in the loadout while avoiding duplicate hits
     * from both port-name and nested port-definition scans.
     */
    private function countCargoGridPorts(array $entries): int
    {
        $hits = [];

        $registerHit = static function (?string $name, ?string $type, mixed $port = null) use (&$hits): void {
            $normalizedName = strtolower(trim((string) $name));
            $normalizedType = strtolower(trim((string) $type));

            $key = $normalizedName !== ''
                ? "name:{$normalizedName}"
                : "type:{$normalizedType}:".md5(json_encode($port, JSON_UNESCAPED_SLASHES));

            $hits[$key] = true;
        };

        $scanPorts = static function (array $ports) use (&$scanPorts, $registerHit): void {
            foreach ($ports as $port) {
                $name = $port['Name'] ?? '';
                $type = Arr::get($port, 'Types.SItemPortDefTypes.Type', '');
                $nameLower = strtolower((string) $name);
                $typeLower = strtolower((string) $type);

                if (($nameLower !== '' && preg_match('/cargo[ _-]?grid/', $nameLower)) || $typeLower === 'cargogrid') {
                    $registerHit($nameLower, $typeLower, $port);
                }

                if (! empty($port['Ports']) && is_array($port['Ports'])) {
                    $scanPorts($port['Ports']);
                }
            }
        };

        $walker = static function (array $items) use (&$walker, $scanPorts, $registerHit): void {
            foreach ($items as $entry) {
                $portName = strtolower((string) ($entry['portName'] ?? ''));
                if ($portName !== '' && preg_match('/cargo[ _-]?grid/', $portName)) {
                    $registerHit($portName, null, ['portName' => $portName]);
                }

                $scanPorts(Arr::get($entry, 'ItemRaw.Components.SItemPortContainerComponentParams.Ports', []));

                if (! empty($entry['entries']) && is_array($entry['entries'])) {
                    $walker($entry['entries']);
                }

                $manualEntries = Arr::get($entry, 'ItemRaw.Components.SEntityComponentDefaultLoadoutParams.loadout.SItemPortLoadoutManualParams.entries', []);
                if (! empty($manualEntries)) {
                    $walker($manualEntries);
                }
            }
        };

        $walker($entries);

        return count($hits);
    }

    /**
     * Detect whether the vehicle has any cargo-related infrastructure in its loadout.
     *
     * Checks for port names matching cargo-related patterns (cargogrid, cargoarea,
     * cargoramp) that indicate the vehicle was designed with cargo capability.
     * Also checks whether cargogrid ports are populated with actual entities.
     *
     * This distinguishes ships like the 135c (has cargoarea/cargoramp but no cargogrid)
     * from ships like the Avenger Stalker (has no cargo ports at all).
     */
    private function hasCargoInfrastructure(array $entries): bool
    {
        $narrowCargoPattern = '/cargogrid|cargoarea|cargoramp/i';
        $broadCargoPattern = '/cargo/i';

        $walker = static function (array $items) use (&$walker, $narrowCargoPattern, $broadCargoPattern): bool {
            foreach ($items as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $portName = (string) ($entry['portName'] ?? '');

                // Narrow pattern (cargogrid, cargoarea, cargoramp): port indicates
                // cargo infrastructure when it has an entity loaded. Empty ports mean
                // the variant intentionally disabled cargo (e.g. Reliant Mako, MPUV Personnel).
                if ($portName !== '' && preg_match($narrowCargoPattern, $portName)) {
                    $entityClass = $entry['className'] ?? $entry['ClassName'] ?? '';
                    $itemRaw = $entry['ItemRaw'] ?? null;

                    if ($entityClass !== '' || is_array($itemRaw)) {
                        return true;
                    }

                    // Port exists but is empty — variant disabled cargo
                    continue;
                }

                // Broad pattern (any "cargo" port): only counts if the loaded entity
                // has a cargo-related class name (CargoGrid, Cargo_Rack, Cargo_Area, etc.).
                // This distinguishes a cargo rack (Nomad) from a cover plate (Mustang Beta).
                if ($portName !== '' && preg_match($broadCargoPattern, $portName)
                    && ! preg_match($narrowCargoPattern, $portName)
                ) {
                    $itemRaw = $entry['ItemRaw'] ?? null;
                    if (is_array($itemRaw)) {
                        $entityClass = strtolower($itemRaw['className'] ?? $itemRaw['ClassName'] ?? '');

                        // Check for cargo-related entity class patterns
                        if ($entityClass !== '' && preg_match('/cargogrid|cargo[ _-]?(rack|area|bed|grid)/i', $entityClass)) {
                            return true;
                        }
                    }
                }

                // Recurse into sub-entries
                if (!empty($entry['entries']) && is_array($entry['entries']) && $walker($entry['entries'])) {
                    return true;
                }

                // Also check manual loadout entries
                $manualEntries = Arr::get($entry, 'ItemRaw.Components.SEntityComponentDefaultLoadoutParams.loadout.SItemPortLoadoutManualParams.entries', []);
                if (!empty($manualEntries) && is_array($manualEntries) && $walker($manualEntries)) {
                    return true;
                }
            }

            return false;
        };

        return $walker($entries);
    }

    private function calculateInlineGridScu(array $item): ?float
    {
        $dimX = Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.interiorDimensions.x');
        $dimY = Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.interiorDimensions.y');
        $dimZ = Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.interiorDimensions.z');

        if ($dimX === null || $dimY === null || $dimZ === null) {
            return null;
        }

        return ($dimX * $dimY * $dimZ) / ScuCalculator::M_TO_SCU_UNIT;
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

    private function hasEquivalentItemInEntries(array $entries, array $installedItem): bool
    {
        foreach ($entries as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $itemRaw = $candidate['ItemRaw'] ?? null;
            if (! is_array($itemRaw)) {
                continue;
            }

            if ($this->isSameItemIdentity($itemRaw, $installedItem)) {
                return true;
            }
        }

        return false;
    }

    private function isSameItemIdentity(array $left, array $right): bool
    {
        $leftUuid = strtolower((string) ($left['__ref'] ?? ''));
        $rightUuid = strtolower((string) ($right['__ref'] ?? ''));

        if ($leftUuid !== '' && $rightUuid !== '' && $leftUuid === $rightUuid) {
            return true;
        }

        $leftPath = strtolower((string) ($left['__path'] ?? ''));
        $rightPath = strtolower((string) ($right['__path'] ?? ''));

        if ($leftPath !== '' && $rightPath !== '' && $leftPath === $rightPath) {
            return true;
        }

        $leftClass = strtolower((string) ($left['className'] ?? $left['ClassName'] ?? ''));
        $rightClass = strtolower((string) ($right['className'] ?? $right['ClassName'] ?? ''));

        return $leftClass !== '' && $rightClass !== '' && $leftClass === $rightClass;
    }
}
