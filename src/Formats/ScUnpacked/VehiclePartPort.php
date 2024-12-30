<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

final class VehiclePartPort extends ItemPort
{
    public function canTransform(): bool
    {
        return $this->item?->nodeName === 'ItemPort';
    }
}
