<?php

namespace Octfx\ScDataDumper\Services\Vehicle\CargoGridStrategies;

use Octfx\ScDataDumper\Helper\Arr;
use Octfx\ScDataDumper\Helper\VehicleWrapper;
use Octfx\ScDataDumper\Services\ServiceFactory;
use Octfx\ScDataDumper\ValueObjects\CargoGridResult;
use Throwable;

/**
 * Convention-based cargo grid class name resolution
 *
 * Tries to find cargo grids using naming conventions like:
 * - {Modification}_CargoGrid
 * - {VehicleClassName}_CargoGrid
 * - {BaseClassName}_CargoGrid
 * - {VehicleClassName}_CargoGrid_{Suffix} (e.g. ORIG_135c_CargoGrid_Rear)
 */
final class ConventionBasedCargoStrategy implements CargoGridStrategyInterface
{
    public function resolve(VehicleWrapper $vehicle, CargoGridResult $result): void
    {
        if (! $result->shouldContinueSearching()) {
            return;
        }

        $inventoryContainerService = ServiceFactory::getInventoryContainerService();
        $vehicleComponent = $vehicle->entity->get('Components/VehicleComponentParams')
            ?? $vehicle->entity->getAttachDef();
        $attachDef = $vehicle->entity->getAttachDef();
        $vehicleClassName = $vehicle->entity->getClassName();

        $classCandidates = [];

        $modification = $vehicleComponent?->get('modification') ?? $attachDef?->get('modification');
        if ($modification) {
            $classCandidates[] = $modification.'_CargoGrid';
        }

        $classCandidates[] = $vehicleClassName.'_CargoGrid';

        $baseVehicleDefinition = (string) (
            $vehicleComponent?->get('vehicleDefinition', '') ??
            $attachDef?->get('vehicleDefinition', '')
        );
        $baseClassName = $baseVehicleDefinition !== '' ? pathinfo($baseVehicleDefinition, PATHINFO_FILENAME) : null;
        $baseClassFromName = str_contains($vehicleClassName, '_')
            ? substr($vehicleClassName, 0, strrpos($vehicleClassName, '_'))
            : null;

        foreach (array_filter(array_unique([$baseClassName, $baseClassFromName])) as $baseCandidate) {
            if ($baseCandidate !== $vehicleClassName) {
                $classCandidates[] = $baseCandidate.'_CargoGrid';
            }
        }

        foreach ($this->collectCargoGridSuffixes($vehicle->loadout) as $suffix) {
            $classCandidates[] = $vehicleClassName.'_CargoGrid_'.$suffix;
        }

        foreach (array_unique($classCandidates) as $className) {
            if (! $result->shouldContinueSearching()) {
                break;
            }

            // Skip templates
            if (str_ends_with(strtolower($className), '_template')) {
                continue;
            }

            try {
                $container = $inventoryContainerService->getByClassName($className);
            } catch (Throwable) {
                $container = null;
            }

            if (! $container || ! $container->isOpenContainer()) {
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

    /**
     * Extract suffix tokens from cargogrid-related port names
     *
     * @return string[]
     */
    private function collectCargoGridSuffixes(array $entries): array
    {
        $suffixes = [];

        $scanPorts = function (array $ports) use (&$scanPorts, &$suffixes): void {
            foreach ($ports as $port) {
                $portName = strtolower($port['Name'] ?? '');
                if ($portName !== '' && str_contains($portName, 'cargogrid')) {
                    $suffix = substr($portName, strpos($portName, 'cargogrid') + strlen('cargogrid'));
                    $suffix = ltrim($suffix, '_-');

                    if ($suffix !== '') {
                        $parts = array_filter(
                            explode('_', $suffix),
                            static fn ($part) => $part !== '' && ! ctype_digit($part)
                        );

                        if (! empty($parts)) {
                            $suffixes[] = $this->toPascalCase(implode('_', $parts));
                        }
                    }
                }

                if (! empty($port['Ports']) && is_array($port['Ports'])) {
                    $scanPorts($port['Ports']);
                }
            }
        };

        $walker = function (array $items) use (&$walker, &$scanPorts): void {
            foreach ($items as $entry) {
                $portName = strtolower($entry['portName'] ?? '');
                if ($portName !== '' && str_contains($portName, 'cargogrid')) {
                    $suffix = substr($portName, strpos($portName, 'cargogrid') + strlen('cargogrid'));
                    $suffix = ltrim($suffix, '_-');

                    if ($suffix !== '') {
                        $parts = array_filter(
                            explode('_', $suffix),
                            static fn ($part) => $part !== '' && ! ctype_digit($part)
                        );

                        if (! empty($parts)) {
                            $suffixes[] = $this->toPascalCase(implode('_', $parts));
                        }
                    }
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

        return array_values(array_unique($suffixes));
    }

    /**
     * Convert string to PascalCase
     */
    private function toPascalCase(string $string): string
    {
        return str_replace('_', '', ucwords($string, '_'));
    }
}
