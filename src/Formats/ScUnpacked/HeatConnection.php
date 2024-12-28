<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class HeatConnection extends BaseFormat
{
    protected ?string $elementKey = 'Components/EntityComponentHeatConnection';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        return $this->get()?->attributesToArray();
    }
}
