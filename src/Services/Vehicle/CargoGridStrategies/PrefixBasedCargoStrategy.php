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
    public function resolve(VehicleWrapper $vehicle, CargoGridResult $result): void
    {
        if (! $result->shouldContinueSearching()) {
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

            $uuid = $container->getUuid();
            if (in_array($uuid, $result->existingGridUuids, true)) {
                continue;
            }

            $scu = (float) ($container->getSCU() ?? 0);
            $result->addContainer($uuid, $container, $scu);
        }
    }
}
