<?php

namespace Octfx\ScDataDumper\Formats\ScUnpacked;

use Octfx\ScDataDumper\Formats\BaseFormat;

final class CargoGrid extends BaseFormat
{
    protected ?string $elementKey = 'Components/SCItemCargoGridParams';

    public function toArray(): ?array
    {
        if (! $this->canTransform()) {
            return null;
        }

        $grid = $this->get();
        $attribs = $grid?->attributesToArray();

        return [
            'Width' => $grid->get('dimensions@x'),
            'Height' => $grid->get('dimensions@z'),
            'Depth' => $grid->get('dimensions@y'),
            'Capacity' => ($grid->get('dimensions@x') * $grid->get('dimensions@y') * $grid->get('dimensions@z')) / M_TO_SCU_UNIT,
        ] + $attribs;
    }
}
