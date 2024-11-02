<?php

namespace Octfx\ScDataDumper\Definitions;

final class RadarSystemSharedParams extends Element
{
    public function toArray(): array
    {
        return $this->toArrayRecursive($this);
    }
}
