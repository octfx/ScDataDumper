<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class RadiationResistance extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemClothingParams/RadiationResistance';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $resistance = $this->get();

        return [
            'MaximumRadiationCapacity' => $resistance->get('MaximumRadiationCapacity'),
            'RadiationDissipationRate' => $resistance->get('RadiationDissipationRate'),
        ];
    }
}
