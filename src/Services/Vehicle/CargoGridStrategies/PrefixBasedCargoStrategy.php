<?php

namespace Octfx\ScDataDumper\Services\Vehicle\CargoGridStrategies;

use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\ValueObjects\CargoGridResult;

/**
 * Prefix-based cargo grid discovery
 *
 * Scans for cargo grid classes that share the vehicle's class prefix
 * (e.g. AEGS_Avenger_CargoGrid_Main, AEGS_Avenger_CargoGrid_Rear)
 */
final class PrefixBasedCargoStrategy implements CargoGridStrategyInterface
{
    use DetectsSiblingVariantGrids;

    public function resolve(VehicleWrapper $vehicle, CargoGridResult $result): void
    {
        if ($this->shouldSkipFallback($result)) {
            return;
        }

        $inventoryContainerService = ServiceFactory::getInventoryContainerService();
        $vehicleClassName = $vehicle->entity->getClassName();

        $containers = $inventoryContainerService->findByClassPrefix($vehicleClassName.'_CargoGrid_');

        usort($containers, static fn ($a, $b) => ($b->getSCU() ?? 0) <=> ($a->getSCU() ?? 0));

        foreach ($containers as $container) {
            if (! $result->shouldContinueSearching()) {
                break;
            }

            if (! $container->isOpenContainer()) {
                continue;
            }

            if (str_ends_with(strtolower($container->getClassName()), '_template')) {
                continue;
            }

            // Skip grids from sibling variants.
            // e.g. CRUS_Starlifter_A2 should not pick up CRUS_Starlifter_CargoGrid_Large_C2.
            if ($this->isSiblingVariantGrid($vehicleClassName, $container->getClassName())) {
                continue;
            }

            // For vehicles without a variant suffix (2-part names), detect sibling variant
            // grids by checking if the grid's non-descriptive suffix matches the loadout.
            if ($this->isUnmatchedVariantCapacityGrid($vehicleClassName, $container->getClassName(), $result)) {
                continue;
            }

            $uuid = $container->getUuid();
            if (in_array($uuid, $result->existingGridUuids, true)) {
                continue;
            }

            $scu = (float) ($container->getSCU() ?? 0);
            $result->addContainer($uuid, $container, $scu);
        }
    }
}
