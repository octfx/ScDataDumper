<?php

namespace Octfx\ScDataDumper\ValueObjects;

use Octfx\ScDataDumper\Helper\Arr;
use Octfx\ScDataDumper\Services\ServiceFactory;

/**
 * Calculate SCU (Standard Cargo Unit) capacity from item data
 *
 * Provides a single source of truth for SCU calculations across the codebase.
 * SCU can be defined in three ways:
 * 1. Direct reference lookup via InventoryContainerService
 * 2. Capacity units (SStandardCargoUnit, SCentiCargoUnit, SMicroCargoUnit)
 * 3. Interior dimensions converted to SCU
 */
final class ScuCalculator
{
    /** Conversion factor from cubic meters to SCU */
    private const M_TO_SCU_UNIT = 1.953125;

    /**
     * Calculate SCU capacity from item data
     *
     * @param  array  $item  Item array with components and references
     * @return float|null SCU capacity, or null if cannot be calculated
     */
    public static function fromItem(array $item): ?float
    {
        $ref = Arr::get($item, '__ref');
        if ($ref) {
            $container = ServiceFactory::getInventoryContainerService()->getByReference($ref);
            if ($container) {
                return $container->getSCU();
            }
        }

        // Try capacity-based calculation
        $capacity = Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.capacity');
        if ($capacity) {
            $scu = self::fromCapacity($capacity);
            if ($scu !== null) {
                return $scu;
            }
        }

        // Try dimension-based calculation
        return self::fromDimensions($item);
    }

    /**
     * Calculate SCU from capacity units
     *
     * @param  array  $capacity  Capacity array with unit definitions
     * @return float|null SCU value or null
     */
    private static function fromCapacity(array $capacity): ?float
    {
        $standard = Arr::get($capacity, 'SStandardCargoUnit.standardCargoUnits');
        if ($standard !== null) {
            return (float) $standard;
        }

        $centi = Arr::get($capacity, 'SCentiCargoUnit.centiSCU');
        if ($centi !== null) {
            return (float) $centi / 100;
        }

        $micro = Arr::get($capacity, 'SMicroCargoUnit.microSCU');
        if ($micro !== null) {
            return (float) $micro / 1_000_000;
        }

        return null;
    }

    /**
     * Calculate SCU from interior dimensions
     *
     * @param  array  $item  Item with dimension data
     * @return float|null SCU calculated from dimensions or null
     */
    private static function fromDimensions(array $item): ?float
    {
        $dimX = Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.interiorDimensions.x');
        $dimY = Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.interiorDimensions.y');
        $dimZ = Arr::get($item, 'Components.SCItemInventoryContainerComponentParams.inventoryContainer.interiorDimensions.z');

        if ($dimX !== null && $dimY !== null && $dimZ !== null) {
            return ($dimX * $dimY * $dimZ) / self::M_TO_SCU_UNIT;
        }

        return null;
    }
}
