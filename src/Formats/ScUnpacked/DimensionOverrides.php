<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class DimensionOverrides extends BaseFormat
{
    protected ?string $elementKey = 'Components/SAttachableComponentParams/AttachDef/inventoryOccupancyDimensionsUIOverride/Vec3';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $override = $this->get();

        return (new Vec3($override, ['x' => 'Width', 'y' => 'Length', 'z' => 'Height']))->toArray();
    }
}
