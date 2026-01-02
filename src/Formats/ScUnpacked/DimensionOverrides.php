<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;
use Octfx\ScDataDumper\ValueObjects\ScuCalculator;

/**
 * @deprecated Use `stdItem.InventoryOccupancy.UIDimensions` instead
 */
final class DimensionOverrides extends BaseFormat
{
    protected ?string $elementKey = 'Components/SAttachableComponentParams/AttachDef/inventoryOccupancyDimensionsUIOverride/Vec3';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $override = $this->get();

        return [
            ...new Vec3($override, ['x' => 'Width', 'y' => 'Length', 'z' => 'Height'])->toArray(),
            'Volume' => ScuCalculator::fromDimensions([
                'x' => $override->get('x'),
                'y' => $override->get('y'),
                'z' => $override->get('z'),
            ]),
        ];
    }
}
