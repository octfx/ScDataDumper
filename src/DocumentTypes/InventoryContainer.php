<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\DocumentTypes;

use Octfx\ScDataDumper\Definitions\Element;

define('M_TO_SCU_UNIT', 1.953125);

final class InventoryContainer extends RootDocument
{
    /**
     * Dimensions of this entity in meter, defined as x, y, z
     *
     * @return float[]
     */
    public function getInteriorDimensions(): array
    {
        return [
            'x' => (float) $this->get('interiorDimensions@x'),
            'y' => (float) $this->get('interiorDimensions@y'),
            'z' => (float) $this->get('interiorDimensions@z'),
        ];
    }

    /**
     * Open Container (Cargo Grid) ONLY
     *
     * @return ?float[]
     */
    public function getMinPermittedItemSize(): ?array
    {
        if (! $this->isOpenContainer()) {
            return null;
        }

        return [
            'x' => (float) $this->get('inventoryType/InventoryOpenContainerType/minPermittedItemSize@x'),
            'y' => (float) $this->get('inventoryType/InventoryOpenContainerType/minPermittedItemSize@y'),
            'z' => (float) $this->get('inventoryType/InventoryOpenContainerType/minPermittedItemSize@z'),
        ];
    }

    /**
     * Open Container (Cargo Grid) ONLY
     *
     * @return ?float[]
     */
    public function getMaxPermittedItemSize(): ?array
    {
        if (! $this->isOpenContainer()) {
            return null;
        }

        return [
            'x' => (float) $this->get('inventoryType/InventoryOpenContainerType/maxPermittedItemSize@x'),
            'y' => (float) $this->get('inventoryType/InventoryOpenContainerType/maxPermittedItemSize@y'),
            'z' => (float) $this->get('inventoryType/InventoryOpenContainerType/maxPermittedItemSize@z'),
        ];
    }

    /**
     * Open Container === Cargo Grid
     */
    public function isOpenContainer(): bool
    {
        return $this->get('inventoryType/InventoryOpenContainerType')?->nodeName === 'InventoryOpenContainerType';
    }

    public function isExternalContainer(): bool
    {
        return $this->isOpenContainer() && ((int) $this->get('inventoryType/InventoryOpenContainerType@isExternalContainer') === 1);
    }

    public function isClosedContainer(): bool
    {
        return $this->get('inventoryType/InventoryClosedContainerType')?->nodeName === 'InventoryClosedContainerType';
    }

    /**
     * The SCU storage capacity of this inventory container
     */
    public function getSCU(): ?float
    {
        if ($this->isOpenContainer()) {
            return $this->getCapacityValue();
        }

        $unit = $this->getScuConversionUnit();
        if (! $unit) {
            return null;
        }

        return round($this->getCapacityValue() * (10 ** -$unit), 4);
    }

    /**
     * The original capacity unit name
     */
    public function getCapacityName(): ?string
    {
        // TODO: Check and verify if Open Containers (Cargo Grids) are always SCU
        if ($this->isOpenContainer()) {
            return 'SCU';
        }

        /** @var \DOMNodeList|null $capacity */
        $capacity = $this->get('inventoryType/InventoryClosedContainerType/capacity')?->childNodes;

        return match ($capacity?->item(0)?->nodeName) {
            'SStandardCargoUnit' => 'SCU',
            'SCentiCargoUnit' => 'cSCU',
            'SMicroCargoUnit' => 'µSCU',
            default => null
        };
    }

    /**
     * The container capacity in its original form
     */
    public function getCapacityValue(): ?float
    {
        // TODO: Check and verify
        if ($this->isOpenContainer()) {
            ['x' => $x, 'y' => $y, 'z' => $z] = $this->getInteriorDimensions();

            return round(($x * $y * $z) / M_TO_SCU_UNIT, 2);
        }

        /** @var Element|null $capacity */
        $capacity = $this->get('inventoryType/InventoryClosedContainerType/capacity');

        return $capacity->get('SStandardCargoUnit@standardCargoUnits') ??
            $capacity->get('SCentiCargoUnit@centiSCU') ??
            $capacity->get('SMicroCargoUnit@microSCU');
    }

    /**
     * Conversion unit for calculating the SCU storage capacity
     */
    public function getScuConversionUnit(): ?int
    {
        if ($this->isOpenContainer()) {
            return 0;
        }

        /** @var \DOMNodeList|null $capacity */
        $capacity = $this->get('inventoryType/InventoryClosedContainerType/capacity')?->childNodes;

        return match ($capacity?->item(0)?->nodeName) {
            'SStandardCargoUnit' => 0,
            'SCentiCargoUnit' => 2,
            'SMicroCargoUnit' => 6,
            default => null
        };
    }

    /**
     * @return array|float[]
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->getUuid(),
            'class' => $this->getClassName(),
            'SCU' => $this->getSCU(),
            'capacity' => $this->getCapacityValue(),
            'capacity_name' => $this->getCapacityName(),
        ] + $this->getInteriorDimensions();
    }
}
