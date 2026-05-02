<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class GForceResistance extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemClothingParams/Flight';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $flight = $this->get();

        return [
            'Value' => $flight->get('@gForceResistance'),
        ];
    }
}
