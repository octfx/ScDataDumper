<?php

namespace Octfx\ScDataDumper\Services\Vehicle\CargoGridStrategies;

use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\ValueObjects\CargoGridResult;

/**
 * Base implementation class cargo grid discovery
 *
 * As a last resort for prefix-based searching, tries to find cargo grids
 * using the base implementation class prefix. For example, if the vehicle
 * is ORIG_135c_variant, it will try ORIG_135c_CargoGrid_*.
 */
final class BaseClassCargoStrategy implements CargoGridStrategyInterface
{
    use DetectsSiblingVariantGrids;

    public function resolve(VehicleWrapper $vehicle, CargoGridResult $result): void
    {
        if ($this->shouldSkipFallback($result)) {
            return;
        }

        $inventoryContainerService = ServiceFactory::getInventoryContainerService();
        $vehicleComponent = $vehicle->entity->get('Components/VehicleComponentParams')
            ?? $vehicle->entity->getAttachDef();
        $vehicleClassName = $vehicle->entity->getClassName();

        // Determine base class name candidates
        $vehicleDefinition = (string) ($vehicleComponent?->get('@vehicleDefinition', '') ?? '');
        $baseClassName = $vehicleDefinition !== '' ? pathinfo($vehicleDefinition, PATHINFO_FILENAME) : null;
        $baseClassFromName = str_contains($vehicleClassName, '_')
            ? substr($vehicleClassName, 0, strrpos($vehicleClassName, '_'))
            : null;

        foreach (array_filter(array_unique([$baseClassName, $baseClassFromName])) as $baseCandidate) {
            if ($baseCandidate === $vehicleClassName) {
                continue; // Already tried this in PrefixBasedCargoStrategy
            }

            $containers = $inventoryContainerService->findByClassPrefix($baseCandidate.'_CargoGrid_');
            usort($containers, static fn ($a, $b) => ($b->getSCU() ?? 0) <=> ($a->getSCU() ?? 0));

            foreach ($containers as $container) {
                if (! $result->shouldContinueSearching()) {
                    break 2;
                }

                if (! $container->isOpenContainer()) {
                    continue;
                }

                if (str_ends_with(strtolower($container->getClassName()), '_template')) {
                    continue;
                }

                // Skip grids from sibling variants
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

            if (! $result->shouldContinueSearching()) {
                break;
            }
        }
    }
}
