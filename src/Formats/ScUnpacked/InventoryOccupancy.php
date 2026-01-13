<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Definitions\Element;
use Octfx\ScDataDumper\DocumentTypes\EntityClassDefinition;
use Octfx\ScDataDumper\Formats\BaseFormat;

/**
 * Inventory occupancy payload as stored by CIG.
 *
 * Key points from the raw data (see sc-data items/ships JSON):
 * - inventoryOccupancyDimensions are physical meters (x=width, y=length/depth, z=height)
 * - inventoryOccupancyLocalBoundsMin/Max define the occupancy box corners in item-local space.  x/y often center
 *   around the pivot (negative to positive), while z usually starts at 0 so the box rests on the pivot plane.
 * - inventoryOccupancyDimensionsUIOverride supplies alternative meter dimensions for UI display only.
 * - inventoryOccupancyVolume may come as SCU / centiSCU / microSCU; we normalize to SCU with source metadata.
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

        $grid = [
            'Width' => round($attach->get('inventoryOccupancyDimensions@x', 0), 2),
            'Length' => round($attach->get('inventoryOccupancyDimensions@y', 0), 2),
            'Height' => round($attach->get('inventoryOccupancyDimensions@z', 0), 2),
        ];

        $ui = new Vec3(
            $attach->get('inventoryOccupancyDimensionsUIOverride/Vec3'),
            ['x' => 'Width', 'y' => 'Length', 'z' => 'Height']
        );

        $volume = $this->buildVolume($attach->get('/inventoryOccupancyVolume'));

        $localBounds = [
            'Min' => $this->buildBounds($attach, 'inventoryOccupancyLocalBoundsMin'),
            'Max' => $this->buildBounds($attach, 'inventoryOccupancyLocalBoundsMax'),
        ];

        return $this->removeNull(
            [
                'Dimensions' => $this->removeNull($grid),
                'UIDimensions' => $ui->toArray(),
                // 'LocalBounds' => $this->removeNull($localBounds),
                'Volume' => $volume,
            ]
        );
    }

    private function buildBounds(Element $attach, string $basePath): ?array
    {
        $bounds = [
            'X' => $attach->get($basePath.'@x'),
            'Y' => $attach->get($basePath.'@y'),
            'Z' => $attach->get($basePath.'@z'),
        ];

        return $this->removeNull($bounds);
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
