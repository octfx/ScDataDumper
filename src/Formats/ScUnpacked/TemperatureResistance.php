<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class TemperatureResistance extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemClothingParams/TemperatureResistance';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $resistance = $this->get();

        return (new MinMax($resistance, 'MinResistance', 'MaxResistance'))->toArray();
    }
}
