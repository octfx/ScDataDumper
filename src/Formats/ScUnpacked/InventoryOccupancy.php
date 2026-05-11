<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Formats\BaseFormat;

/**
 * Inventory occupancy payload as stored by CIG.
 *
 * Key points from the raw data (see sc-data items/ships JSON):
 * - inventoryOccupancyDimensions are cargo-grid slot dimensions in meters (x=width, y=length/depth, z=height).
 * - inventoryOccupancyLocalBoundsMin/Max define the 3D model AABB corners in item-local space.
 *   When non-zero, these represent the true physical dimensions of the item. x/y often center around the pivot
 *   (negative to positive), while z usually starts at 0 so the box rests on the pivot plane.
 *   When both Min and Max are (0,0,0), no model bounds are available and we fall back to cargo-grid dims.
 * - inventoryOccupancyDimensionsUIOverride supplies alternative meter dimensions for UI display only.
 * - inventoryOccupancyVolume may come as SCU / centiSCU / microSCU; we normalize to SCU with source metadata.
 *
 * Output structure:
 * - Dimensions: true physical size from localBounds when available, otherwise falls back to cargo-grid dims.
 * - CargoGrid: cargo-grid slot dimensions (how much space the item occupies in inventory).
 * - UIDimensions: CIG's UI display override dimensions.
 * - Volume: SCU-normalized volume.
 */
final class InventoryOccupancy extends BaseFormat
{
    protected ?string $elementKey = 'Components/SAttachableComponentParams/AttachDef';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        /** @var Element $attach */
        $attach = $this->get();

        // Cargo-grid slot dimensions (inventory occupancy)
        $cargoGrid = [
            'Width' => round($attach->get('inventoryOccupancyDimensions@x', 0), 2),
            'Length' => round($attach->get('inventoryOccupancyDimensions@y', 0), 2),
            'Height' => round($attach->get('inventoryOccupancyDimensions@z', 0), 2),
        ];

        // True physical dimensions from 3D model bounding box
        $dimensions = $this->buildDimensionsFromBounds($attach) ?? $this->removeNull($cargoGrid);

        $ui = new Vec3(
            $attach->get('inventoryOccupancyDimensionsUIOverride/Vec3'),
            ['x' => 'Width', 'y' => 'Length', 'z' => 'Height']
        );

        $volume = $this->buildVolume($attach->get('inventoryOccupancyVolume'));

        return $this->removeNull(
            [
                'Dimensions' => $dimensions,
                'CargoGrid' => $this->removeNull($cargoGrid),
                'UIDimensions' => $ui->toArray(),
                'Volume' => $volume,
            ]
        );
    }

    /**
     * Derive physical dimensions from the 3D model local bounding box.
     *
     * Returns {Width, Length, Height} in meters, or null when bounds are zeroed out
     * (indicating no model bounds data is available).
     */
    private function buildDimensionsFromBounds(Element $attach): ?array
    {
        $minX = $attach->get('@inventoryOccupancyLocalBoundsMin@x');
        $minY = $attach->get('@inventoryOccupancyLocalBoundsMin@y');
        $minZ = $attach->get('@inventoryOccupancyLocalBoundsMin@z');
        $maxX = $attach->get('@inventoryOccupancyLocalBoundsMax@x');
        $maxY = $attach->get('@inventoryOccupancyLocalBoundsMax@y');
        $maxZ = $attach->get('@inventoryOccupancyLocalBoundsMax@z');

        // All-zero bounds mean no model data available
        if (($minX == 0 && $minY == 0 && $minZ == 0 && $maxX == 0 && $maxY == 0 && $maxZ == 0)) {
            return null;
        }

        return [
            'Width' => round(abs((float) $maxX - (float) $minX), 4),
            'Length' => round(abs((float) $maxY - (float) $minY), 4),
            'Height' => round(abs((float) $maxZ - (float) $minZ), 4),
        ];
    }

    private function buildVolume(Element|EntityClassDefinition|null $node): ?array
    {
        if ($node === null) {
            return null;
        }

        $scu = null;
        $source = null;
        $raw = null;

        if ($node->get('SStandardCargoUnit@standardCargoUnits') !== null) {
            $raw = $node->get('SStandardCargoUnit@standardCargoUnits');
            $source = 'SCU';
            $scu = $raw;
        } elseif ($node->get('SCentiCargoUnit@centiSCU') !== null) {
            $raw = $node->get('SCentiCargoUnit@centiSCU');
            $source = 'cSCU';
            $scu = $raw * (10 ** -2);
        } elseif ($node->get('SMicroCargoUnit@microSCU') !== null) {
            $raw = $node->get('SMicroCargoUnit@microSCU');
            $source = 'µSCU';
            $scu = $raw * (10 ** -6);
        }

        if ($scu === null) {
            return null;
        }

        $scu = round($scu, 4);

        return [
            'SCU' => $scu,
            'SCUConverted' => $raw,
            'Unit' => $source,
        ];
    }

    private function removeNull(array $value): ?array
    {
        $filtered = array_filter($value, static fn ($v) => $v !== null);

        return empty($filtered) ? null : $filtered;
    }
}
