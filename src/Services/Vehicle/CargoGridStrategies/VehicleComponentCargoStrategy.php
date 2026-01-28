<?php

namespace Octfx\ScDataDumper\Services\Vehicle\CargoGridStrategies;

use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\ValueObjects\CargoGridResult;

/**
 * Vehicle component inventory container resolution
 *
 * As a last resort, uses an inventory container attached directly to the
 * vehicle component via the inventoryContainerParams reference.
 */
final class VehicleComponentCargoStrategy implements CargoGridStrategyInterface
{
    public function resolve(VehicleWrapper $vehicle, CargoGridResult $result): void
    {
        if ($result->totalCapacity > 0) {
            return;
        }

        $inventoryContainerService = ServiceFactory::getInventoryContainerService();
        $vehicleComponent = $vehicle->entity->get('Components/VehicleComponentParams')
            ?? $vehicle->entity->getAttachDef();

        $inventoryContainerRef = $vehicleComponent?->get('@inventoryContainerParams');
        if (! $inventoryContainerRef) {
            return;
        }

        $container = $inventoryContainerService->getByReference($inventoryContainerRef);
        if (! $container) {
            return;
        }

        $className = strtolower($container->getClassName() ?? '');
        if ($className !== '' && str_ends_with($className, '_template')) {
            return;
        }

        $uuid = $container->getUuid();
        if (in_array($uuid, $result->existingGridUuids, true)) {
            return;
        }

        $scu = (float) ($container->getSCU() ?? 0);
        $result->addContainer($uuid, $container, $scu);
    }
}
